<?php

namespace App\Http\Controllers;

use App\Models\BillingRecord;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->respond([
            'properties' => Property::query()->count(),
            'active_properties' => Property::query()->where('status', Property::STATUS_ACTIVE)->count(),
            'rooms' => Room::query()->count(),
            'occupied_spaces' => RoomAssignment::query()->where('status', RoomAssignment::STATUS_ACTIVE)->count(),
            'reserved_spaces' => RoomAssignment::query()->where('status', RoomAssignment::STATUS_RESERVED)->count(),
            'total_capacity' => (int) Room::query()->sum('capacity'),
            'active_tenants' => Tenant::query()->whereHas('leases', fn ($leases) => $leases->where('status', Lease::STATUS_ACTIVE))->count(),
            'unpaid_bills' => BillingRecord::query()->whereIn('status', [BillingRecord::STATUS_UNPAID, BillingRecord::STATUS_PARTIAL])->count(),
            'overdue_bills' => BillingRecord::query()
                ->whereIn('status', [BillingRecord::STATUS_UNPAID, BillingRecord::STATUS_PARTIAL])
                ->whereDate('due_date', '<', today())
                ->count(),
            'outstanding_balance' => BillingRecord::totalOutstanding(),
        ]);
    }
}
