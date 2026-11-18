<?php

namespace App\Http\Controllers;

use App\Models\BillingRecord;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class AnalyticsController extends Controller
{
    public function index(): JsonResponse
    {
        $start = today()->startOfMonth()->subMonths(5);

        $billed = $this->sumByMonth(
            BillingRecord::query()
                ->where('status', '!=', BillingRecord::STATUS_CANCELLED)
                ->whereDate('billing_date', '>=', $start->toDateString())
                ->get(['billing_date', 'amount']),
            'billing_date',
            'amount'
        );

        $collected = $this->sumByMonth(
            Payment::query()
                ->whereNull('voided_at')
                ->whereDate('payment_date', '>=', $start->toDateString())
                ->get(['payment_date', 'amount_paid']),
            'payment_date',
            'amount_paid'
        );

        $months = [];
        for ($offset = 0; $offset < 6; $offset++) {
            $month = $start->copy()->addMonths($offset);
            $key = $month->format('Y-m');
            $months[] = [
                'key' => $key,
                'label' => $month->format('M Y'),
                'billed' => $billed[$key] ?? '0.00',
                'collected' => $collected[$key] ?? '0.00',
            ];
        }

        $capacity = Room::query()
            ->selectRaw('property_id, SUM(capacity) as capacity')
            ->groupBy('property_id')
            ->pluck('capacity', 'property_id');

        $occupied = RoomAssignment::query()
            ->join('rooms', 'rooms.id', '=', 'room_assignments.room_id')
            ->where('room_assignments.status', RoomAssignment::STATUS_ACTIVE)
            ->groupBy('rooms.property_id')
            ->selectRaw('rooms.property_id as property_id, COUNT(*) as occupied')
            ->pluck('occupied', 'property_id');

        $occupancy = Property::query()->orderBy('name')->get(['id', 'name'])->map(function (Property $property) use ($capacity, $occupied) {
            return [
                'property' => $property->name,
                'occupied' => (int) ($occupied[$property->id] ?? 0),
                'capacity' => (int) ($capacity[$property->id] ?? 0),
            ];
        })->values();

        $counts = BillingRecord::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->respond([
            'months' => $months,
            'occupancy' => $occupancy,
            'bill_counts' => [
                'unpaid' => (int) ($counts[BillingRecord::STATUS_UNPAID] ?? 0),
                'partially_paid' => (int) ($counts[BillingRecord::STATUS_PARTIAL] ?? 0),
                'paid' => (int) ($counts[BillingRecord::STATUS_PAID] ?? 0),
                'cancelled' => (int) ($counts[BillingRecord::STATUS_CANCELLED] ?? 0),
            ],
        ]);
    }

    /**
     * @param  Collection<int, Model>  $rows
     * @return array<string, string>
     */
    private function sumByMonth($rows, string $dateField, string $amountField): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $key = $row->{$dateField}->format('Y-m');
            $totals[$key] = Money::add($totals[$key] ?? '0', $row->{$amountField});
        }

        return $totals;
    }
}
