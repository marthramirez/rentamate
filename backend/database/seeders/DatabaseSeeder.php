<?php

namespace Database\Seeders;

use App\Models\BillingRecord;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        $admin->forceFill([
            'role' => 'admin',
            'status' => 'active',
        ])->save();

        if (Property::query()->where('name', 'Maple Residences')->exists()) {
            return;
        }

        DB::transaction(function () {
            $property = Property::create([
                'name' => 'Maple Residences',
                'address' => '88 Mabini Street, Quezon City',
                'contact_number' => '09171234567',
                'description' => 'Three-storey boarding house near the university.',
                'status' => 'active',
            ]);

            $room101 = Room::create([
                'property_id' => $property->id,
                'room_number' => '101',
                'floor' => '1',
                'capacity' => 2,
                'monthly_rate' => 4500,
                'description' => 'Shared room with two beds.',
                'status' => 'active',
            ]);

            Room::create([
                'property_id' => $property->id,
                'room_number' => '102',
                'floor' => '1',
                'capacity' => 1,
                'monthly_rate' => 6000,
                'description' => 'Single room.',
                'status' => 'active',
            ]);

            Room::create([
                'property_id' => $property->id,
                'room_number' => '201',
                'floor' => '2',
                'capacity' => 1,
                'monthly_rate' => 5000,
                'description' => 'Closed for repairs.',
                'status' => 'maintenance',
            ]);

            $juan = Tenant::create([
                'first_name' => 'Juan',
                'middle_name' => 'Santos',
                'last_name' => 'Dela Cruz',
                'email' => 'juan@example.com',
                'contact_number' => '09180001111',
                'address' => 'Quezon City',
                'emergency_contact_name' => 'Rosa Dela Cruz',
                'emergency_contact_number' => '09180003333',
                'status' => 'active',
            ]);

            Tenant::create([
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'contact_number' => '09180002222',
                'email' => 'maria@example.com',
                'status' => 'active',
            ]);

            $lease = Lease::create([
                'tenant_id' => $juan->id,
                'room_id' => $room101->id,
                'lease_number' => 'LSE-SEED-0001',
                'start_date' => now()->startOfMonth()->toDateString(),
                'monthly_rent' => 4500,
                'deposit_amount' => 4500,
                'terms' => 'Rent is due on the 5th of each month.',
                'status' => 'draft',
            ]);

            $lease->assignRoom(now()->startOfMonth()->toDateString(), 'active');

            $rent = BillingRecord::create([
                'lease_id' => $lease->id,
                'billing_number' => 'BIL-SEED-0001',
                'billing_date' => now()->subDays(10)->toDateString(),
                'due_date' => now()->subDays(5)->toDateString(),
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'description' => 'Monthly rent',
                'amount' => 4500,
                'status' => 'unpaid',
            ]);

            Payment::create([
                'billing_record_id' => $rent->id,
                'payment_reference' => 'SEED-0001',
                'payment_date' => now()->subDays(4)->toDateString(),
                'amount_paid' => 2000,
                'payment_method' => 'cash',
                'notes' => 'Partial rent payment',
            ]);

            $rent->recalculateStatus();

            BillingRecord::create([
                'lease_id' => $lease->id,
                'billing_number' => 'BIL-SEED-0002',
                'billing_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'description' => 'Water bill',
                'amount' => 300,
                'status' => 'unpaid',
            ]);
        });
    }
}
