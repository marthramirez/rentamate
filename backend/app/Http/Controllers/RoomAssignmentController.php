<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\RoomAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoomAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:reserved,active,completed,cancelled'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'lease_id' => ['nullable', 'integer', 'exists:leases,id'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);

        $query = RoomAssignment::query()
            ->with(['room.property', 'lease.tenant'])
            ->orderByDesc('move_in_date')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->integer('room_id'));
        }

        if ($request->filled('lease_id')) {
            $query->where('lease_id', $request->integer('lease_id'));
        }

        if ($request->filled('tenant_id')) {
            $query->whereHas('lease', fn ($lease) => $lease->where('tenant_id', $request->integer('tenant_id')));
        }

        if ($request->filled('property_id')) {
            $query->whereHas('room', fn ($room) => $room->where('property_id', $request->integer('property_id')));
        }

        if ($request->boolean('all')) {
            return $this->respond($query->limit(500)->get());
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function show(RoomAssignment $roomAssignment): JsonResponse
    {
        $roomAssignment->load(['room.property', 'lease.tenant', 'lease.room']);

        return $this->respond($roomAssignment);
    }

    public function moveOut(Request $request, RoomAssignment $roomAssignment): JsonResponse
    {
        $data = $request->validate([
            'move_out_date' => ['required', 'date'],
            'terminate_lease' => ['nullable', 'boolean'],
        ]);

        $terminate = $request->exists('terminate_lease') ? $request->boolean('terminate_lease') : true;

        DB::transaction(function () use ($roomAssignment, $data, $terminate) {
            $locked = RoomAssignment::query()->whereKey($roomAssignment->id)->lockForUpdate()->firstOrFail();
            $locked->moveOut($data['move_out_date'], $terminate);
        });

        return $this->respond(
            RoomAssignment::query()->with(['room.property', 'lease.tenant'])->findOrFail($roomAssignment->id),
            'Move-out recorded. The assignment history was kept.'
        );
    }

    public function transfer(Request $request, RoomAssignment $roomAssignment): JsonResponse
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'move_out_date' => ['required', 'date'],
            'start_date' => ['required', 'date', 'after_or_equal:move_out_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'monthly_rent' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'lease_number' => ['nullable', 'string', 'max:50', Rule::unique('leases', 'lease_number')],
        ]);

        $lease = DB::transaction(function () use ($roomAssignment, $data) {
            $locked = RoomAssignment::query()->whereKey($roomAssignment->id)->lockForUpdate()->firstOrFail();

            if ((int) $data['room_id'] === (int) $locked->room_id) {
                throw ValidationException::withMessages([
                    'room_id' => 'Choose a different room. The current lease can be edited if only the rate changes.',
                ]);
            }

            $currentLease = Lease::query()->whereKey($locked->lease_id)->lockForUpdate()->firstOrFail();
            $locked->moveOut($data['move_out_date'], true);

            $newLease = Lease::create([
                'tenant_id' => $currentLease->tenant_id,
                'room_id' => $data['room_id'],
                'lease_number' => ($data['lease_number'] ?? null) ?: Lease::generateNumber(),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'monthly_rent' => $data['monthly_rent'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'terms' => $data['terms'] ?? null,
                'status' => Lease::STATUS_DRAFT,
            ]);

            $newLease->assignRoom($data['start_date'], RoomAssignment::STATUS_ACTIVE);

            return $newLease;
        });

        return $this->respond(
            Lease::query()->with(['tenant', 'room.property', 'assignments.room'])->findOrFail($lease->id),
            'Tenant moved to the new room. The previous assignment was kept.',
            201
        );
    }
}
