<?php

namespace Tests\Feature;

use App\Models\BillingRecord;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardingHouseApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    public function test_property_and_room_crud_with_unique_room_numbers(): void
    {
        $created = $this->postJson('/api/properties', [
            'name' => 'North House',
            'address' => '10 Rizal Ave',
            'contact_number' => '09170000001',
        ])->assertCreated();

        $propertyId = $created->json('data.id');

        $this->postJson('/api/rooms', [
            'property_id' => $propertyId,
            'room_number' => '101',
            'capacity' => 2,
            'monthly_rate' => 3500,
        ])->assertCreated()
            ->assertJsonPath('data.available_spaces', 2);

        $this->postJson('/api/rooms', [
            'property_id' => $propertyId,
            'room_number' => '101',
            'capacity' => 1,
            'monthly_rate' => 3000,
        ])->assertStatus(422)->assertJsonValidationErrors('room_number');

        $other = $this->property();
        $this->postJson('/api/rooms', [
            'property_id' => $other->id,
            'room_number' => '101',
            'capacity' => 1,
            'monthly_rate' => 3000,
        ])->assertCreated();

        $this->deleteJson('/api/properties/'.$propertyId)
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('properties', ['id' => $propertyId, 'status' => 'inactive']);

        $empty = $this->property(['name' => 'Empty House']);
        $this->deleteJson('/api/properties/'.$empty->id)
            ->assertOk()
            ->assertJsonPath('deleted', true);
        $this->assertDatabaseMissing('properties', ['id' => $empty->id]);
    }

    public function test_allocation_respects_capacity_room_status_and_one_active_lease(): void
    {
        $property = $this->property();
        $room = $this->room($property, ['capacity' => 1]);
        $maintenance = $this->room($property, ['room_number' => '9', 'status' => 'maintenance', 'capacity' => 2]);
        $first = $this->tenant(['first_name' => 'Ana']);
        $second = $this->tenant(['first_name' => 'Ben']);

        $this->postJson('/api/leases', $this->leasePayload($first, $room))->assertCreated();

        $this->postJson('/api/leases', $this->leasePayload($second, $room))
            ->assertStatus(422)
            ->assertJsonValidationErrors('room_id');

        $this->postJson('/api/leases', $this->leasePayload($second, $maintenance))
            ->assertStatus(422)
            ->assertJsonValidationErrors('room_id');

        $property->update(['status' => 'inactive']);
        $openRoom = $this->room($property, ['room_number' => '8', 'capacity' => 1]);
        $this->postJson('/api/leases', $this->leasePayload($second, $openRoom))
            ->assertStatus(422)
            ->assertJsonValidationErrors('room_id');

        $property->update(['status' => 'active']);
        $this->postJson('/api/leases', $this->leasePayload($first, $openRoom))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tenant_id');
    }

    public function test_move_out_keeps_history_and_frees_capacity(): void
    {
        $property = $this->property();
        $room = $this->room($property, ['capacity' => 1]);
        $tenant = $this->tenant();
        $next = $this->tenant(['first_name' => 'Liza']);

        $lease = $this->postJson('/api/leases', $this->leasePayload($tenant, $room))->assertCreated();
        $assignmentId = $lease->json('data.assignments.0.id');

        $this->postJson('/api/room-assignments/'.$assignmentId.'/move-out', [
            'move_out_date' => '2026-11-20',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.move_out_date', '2026-11-20');

        $this->assertDatabaseHas('room_assignments', [
            'id' => $assignmentId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('leases', [
            'id' => $lease->json('data.id'),
            'status' => 'terminated',
        ]);

        $this->postJson('/api/room-assignments/'.$assignmentId.'/move-out', [
            'move_out_date' => '2026-11-10',
        ])->assertStatus(422);

        $this->postJson('/api/leases', $this->leasePayload($next, $room))->assertCreated();

        $this->getJson('/api/rooms/'.$room->id)
            ->assertOk()
            ->assertJsonPath('data.active_occupancy', 1)
            ->assertJsonPath('data.available_spaces', 0);
    }

    public function test_allocate_uses_the_lease_room_and_reservation_holds_capacity(): void
    {
        $property = $this->property();
        $room = $this->room($property, ['capacity' => 1, 'room_number' => '1']);
        $other = $this->room($property, ['capacity' => 1, 'room_number' => '2']);
        $tenant = $this->tenant();

        $draft = $this->postJson('/api/leases', [
            'tenant_id' => $tenant->id,
            'room_id' => $room->id,
            'start_date' => '2026-11-01',
            'monthly_rent' => 4000,
            'status' => 'draft',
        ])->assertCreated()->json('data');

        $this->postJson('/api/leases/'.$draft['id'].'/allocate', [
            'move_in_date' => '2026-11-02',
            'assignment_status' => 'reserved',
            'room_id' => $other->id,
        ])->assertCreated();

        $this->assertDatabaseHas('room_assignments', [
            'lease_id' => $draft['id'],
            'room_id' => $room->id,
            'status' => 'reserved',
        ]);
        $this->assertDatabaseMissing('room_assignments', [
            'room_id' => $other->id,
        ]);

        $available = $this->getJson('/api/rooms?available=1&all=1')->assertOk()->json('data');
        $availableIds = collect($available)->pluck('id');
        $this->assertFalse($availableIds->contains($room->id));
        $this->assertTrue($availableIds->contains($other->id));
    }

    public function test_transfer_ends_the_old_stay_and_opens_a_new_lease(): void
    {
        $property = $this->property();
        $from = $this->room($property, ['room_number' => 'A', 'capacity' => 1]);
        $to = $this->room($property, ['room_number' => 'B', 'capacity' => 1, 'monthly_rate' => 5500]);
        $tenant = $this->tenant();

        $lease = $this->postJson('/api/leases', $this->leasePayload($tenant, $from))->assertCreated();
        $assignmentId = $lease->json('data.assignments.0.id');

        $moved = $this->postJson('/api/room-assignments/'.$assignmentId.'/transfer', [
            'room_id' => $to->id,
            'move_out_date' => '2026-11-15',
            'start_date' => '2026-11-15',
            'monthly_rent' => 5500,
            'deposit_amount' => 0,
        ])->assertCreated();

        $moved->assertJsonPath('data.room_id', $to->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.monthly_rent', '5500.00');

        $this->assertSame(
            '2026-11-15',
            RoomAssignment::query()->findOrFail($assignmentId)->move_out_date->toDateString()
        );
        $this->assertDatabaseHas('leases', [
            'id' => $lease->json('data.id'),
            'status' => 'terminated',
        ]);
        $this->assertSame(1, $tenant->fresh()->leases()->where('status', 'active')->count());
    }

    public function test_partial_payments_balance_overpayment_void_and_overdue(): void
    {
        $property = $this->property();
        $room = $this->room($property);
        $tenant = $this->tenant();
        $leaseId = $this->postJson('/api/leases', $this->leasePayload($tenant, $room))->json('data.id');

        $bill = $this->postJson('/api/billing-records', [
            'lease_id' => $leaseId,
            'description' => 'Monthly rent',
            'amount' => 1000,
            'billing_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
        ])->assertCreated();

        $billId = $bill->json('data.id');
        $bill->assertJsonPath('data.computed_status', 'overdue')
            ->assertJsonPath('data.balance', '1000.00')
            ->assertJsonPath('data.status', 'unpaid');

        $this->postJson('/api/billing-records/'.$billId.'/payments', [
            'payment_date' => now()->toDateString(),
            'amount_paid' => 400,
            'payment_method' => 'gcash',
            'payment_reference' => 'REF-1',
        ])->assertCreated()
            ->assertJsonPath('data.amount_paid', '400.00')
            ->assertJsonPath('data.balance', '600.00')
            ->assertJsonPath('data.computed_status', 'overdue');

        $this->getJson('/api/billing-records?status=overdue')
            ->assertOk()
            ->assertJsonFragment(['id' => $billId]);

        $this->postJson('/api/billing-records/'.$billId.'/payments', [
            'payment_date' => now()->toDateString(),
            'amount_paid' => 700,
            'payment_method' => 'cash',
        ])->assertStatus(422)->assertJsonValidationErrors('amount_paid');

        $this->postJson('/api/billing-records/'.$billId.'/payments', [
            'payment_date' => now()->toDateString(),
            'amount_paid' => 600,
            'payment_method' => 'cash',
            'payment_reference' => 'REF-1',
        ])->assertStatus(422)->assertJsonValidationErrors('payment_reference');

        $this->postJson('/api/billing-records/'.$billId.'/payments', [
            'payment_date' => now()->toDateString(),
            'amount_paid' => 600,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'REF-2',
        ])->assertCreated()
            ->assertJsonPath('data.balance', '0.00')
            ->assertJsonPath('data.computed_status', 'paid')
            ->assertJsonPath('data.status', 'paid');

        $this->assertSame(2, Payment::query()->where('billing_record_id', $billId)->count());

        $partial = $this->postJson('/api/billing-records', [
            'lease_id' => $leaseId,
            'description' => 'Water bill',
            'amount' => 300,
            'billing_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
        ])->assertCreated()->json('data.id');

        $paymentId = $this->postJson('/api/billing-records/'.$partial.'/payments', [
            'payment_date' => now()->toDateString(),
            'amount_paid' => 100,
            'payment_method' => 'cash',
        ])->json('data.payments.0.id');

        $this->postJson('/api/billing-records/'.$partial.'/cancel')
            ->assertStatus(422);

        $this->postJson('/api/payments/'.$paymentId.'/void', [
            'void_reason' => 'Recorded against the wrong bill',
        ])->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $paymentId]);
        $this->assertNotNull(Payment::query()->find($paymentId)->voided_at);

        $this->getJson('/api/billing-records/'.$partial)
            ->assertOk()
            ->assertJsonPath('data.amount_paid', '0.00')
            ->assertJsonPath('data.balance', '300.00')
            ->assertJsonPath('data.status', 'unpaid');

        $this->postJson('/api/billing-records/'.$partial.'/cancel')->assertOk()
            ->assertJsonPath('data.computed_status', 'cancelled');

        $this->postJson('/api/billing-records/'.$partial.'/payments', [
            'payment_date' => now()->toDateString(),
            'amount_paid' => 50,
            'payment_method' => 'other',
        ])->assertStatus(422);
    }

    public function test_dashboard_reads_database_records(): void
    {
        $property = $this->property();
        $room = $this->room($property, ['capacity' => 3, 'monthly_rate' => 2000]);
        $tenant = $this->tenant();
        $leaseId = $this->postJson('/api/leases', $this->leasePayload($tenant, $room, [
            'monthly_rent' => 2000,
        ]))->json('data.id');

        $billId = $this->postJson('/api/billing-records', [
            'lease_id' => $leaseId,
            'description' => 'Monthly rent',
            'amount' => 2000,
            'billing_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
        ])->json('data.id');

        $this->postJson('/api/billing-records/'.$billId.'/payments', [
            'payment_date' => now()->toDateString(),
            'amount_paid' => 500,
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.properties', 1)
            ->assertJsonPath('data.rooms', 1)
            ->assertJsonPath('data.occupied_spaces', 1)
            ->assertJsonPath('data.total_capacity', 3)
            ->assertJsonPath('data.active_tenants', 1)
            ->assertJsonPath('data.unpaid_bills', 1)
            ->assertJsonPath('data.overdue_bills', 1)
            ->assertJsonPath('data.outstanding_balance', '1500.00');
    }

    public function test_analytics_charts_use_active_money_and_occupancy(): void
    {
        $property = $this->property(['name' => 'North House']);
        $room = $this->room($property, ['capacity' => 3]);
        $tenant = $this->tenant();
        $leaseId = $this->postJson('/api/leases', $this->leasePayload($tenant, $room))->json('data.id');
        $month = now()->startOfMonth()->toDateString();

        $this->postJson('/api/billing-records', [
            'lease_id' => $leaseId,
            'description' => 'Cancelled charge',
            'amount' => 300,
            'billing_date' => $month,
            'due_date' => $month,
        ])->assertCreated();
        $cancelledId = BillingRecord::query()->where('description', 'Cancelled charge')->value('id');
        $this->postJson('/api/billing-records/'.$cancelledId.'/cancel')->assertOk();

        $billId = $this->postJson('/api/billing-records', [
            'lease_id' => $leaseId,
            'description' => 'Monthly rent',
            'amount' => 2000,
            'billing_date' => $month,
            'due_date' => $month,
        ])->json('data.id');

        $paymentId = $this->postJson('/api/billing-records/'.$billId.'/payments', [
            'payment_date' => $month,
            'amount_paid' => 500,
            'payment_method' => 'cash',
        ])->json('data.id');

        $voidId = $this->postJson('/api/billing-records/'.$billId.'/payments', [
            'payment_date' => $month,
            'amount_paid' => 100,
            'payment_method' => 'cash',
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/payments/'.$voidId.'/void', [
            'void_reason' => 'Recorded twice',
        ])->assertOk();

        $response = $this->getJson('/api/analytics')->assertOk();
        $current = collect($response->json('data.months'))->firstWhere('key', now()->format('Y-m'));

        $this->assertCount(6, $response->json('data.months'));
        $this->assertSame('2000.00', $current['billed']);
        $this->assertSame('500.00', $current['collected']);
        $this->assertNotNull($paymentId);
        $response->assertJsonPath('data.occupancy.0.property', 'North House')
            ->assertJsonPath('data.occupancy.0.occupied', 1)
            ->assertJsonPath('data.occupancy.0.capacity', 3)
            ->assertJsonPath('data.bill_counts.partially_paid', 1)
            ->assertJsonPath('data.bill_counts.cancelled', 1)
            ->assertJsonPath('data.bill_counts.unpaid', 0);
    }

    public function test_room_capacity_cannot_drop_below_current_occupancy(): void
    {
        $property = $this->property();
        $room = $this->room($property, ['capacity' => 2]);
        $tenant = $this->tenant();
        $this->postJson('/api/leases', $this->leasePayload($tenant, $room))->assertCreated();
        $this->postJson('/api/leases', $this->leasePayload($this->tenant(['first_name' => 'Ben']), $room))->assertCreated();

        $this->putJson('/api/rooms/'.$room->id, [
            'property_id' => $property->id,
            'room_number' => $room->room_number,
            'capacity' => 1,
            'monthly_rate' => 4000,
            'status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors('capacity');

        $this->putJson('/api/rooms/'.$room->id, [
            'property_id' => $property->id,
            'room_number' => $room->room_number,
            'capacity' => 2,
            'monthly_rate' => 4200,
            'status' => 'active',
        ])->assertOk()->assertJsonPath('data.monthly_rate', '4200.00');

        $this->assertSame('4000.00', $tenant->leases()->first()->monthly_rent);
    }

    public function test_deleting_a_room_with_history_deactivates_it(): void
    {
        $property = $this->property();
        $room = $this->room($property);
        $tenant = $this->tenant();
        $this->postJson('/api/leases', $this->leasePayload($tenant, $room))->assertCreated();

        $this->deleteJson('/api/rooms/'.$room->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('room_assignments', ['room_id' => $room->id, 'status' => 'active']);
    }

    private function property(array $overrides = []): Property
    {
        return Property::create(array_merge([
            'name' => 'House '.fake()->unique()->numerify('###'),
            'address' => '1 Main Street',
            'status' => 'active',
        ], $overrides));
    }

    private function room(Property $property, array $overrides = []): Room
    {
        return Room::create(array_merge([
            'property_id' => $property->id,
            'room_number' => (string) fake()->unique()->numerify('###'),
            'capacity' => 2,
            'monthly_rate' => 4000,
            'status' => 'active',
        ], $overrides));
    }

    private function tenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'contact_number' => '09'.fake()->unique()->numerify('#########'),
            'status' => 'active',
        ], $overrides));
    }

    private function leasePayload(Tenant $tenant, Room $room, array $overrides = []): array
    {
        return array_merge([
            'tenant_id' => $tenant->id,
            'room_id' => $room->id,
            'start_date' => '2026-11-01',
            'monthly_rent' => 4000,
            'deposit_amount' => 0,
            'status' => 'active',
            'move_in_date' => '2026-11-01',
        ], $overrides);
    }
}
