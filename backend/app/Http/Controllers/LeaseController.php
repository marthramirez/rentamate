<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:draft,active,expired,terminated,cancelled'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Lease::query()
            ->with([
                'tenant',
                'room.property',
                'assignments' => fn ($assignments) => $assignments
                    ->whereIn('status', ['active', 'reserved'])
                    ->orderByDesc('id'),
            ])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->integer('tenant_id'));
        }

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->integer('room_id'));
        }

        if ($request->filled('property_id')) {
            $query->whereHas('room', fn ($room) => $room->where('property_id', $request->integer('property_id')));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($builder) use ($search) {
                $builder->where('lease_number', 'like', $search)
                    ->orWhereHas('tenant', function ($tenant) use ($search) {
                        $tenant->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search);
                    });
            });
        }

        if ($request->boolean('all')) {
            return $this->respond($query->limit(500)->get());
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->input('status', Lease::STATUS_DRAFT) === Lease::STATUS_ACTIVE && ! $request->filled('move_in_date')) {
            $request->merge(['move_in_date' => $request->input('start_date')]);
        }

        $data = $this->validated($request);

        $lease = DB::transaction(function () use ($data) {
            $lease = Lease::create([
                'tenant_id' => $data['tenant_id'],
                'room_id' => $data['room_id'],
                'lease_number' => ($data['lease_number'] ?? null) ?: Lease::generateNumber(),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'monthly_rent' => $data['monthly_rent'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'terms' => $data['terms'] ?? null,
                'status' => Lease::STATUS_DRAFT,
            ]);

            if (($data['status'] ?? Lease::STATUS_DRAFT) === Lease::STATUS_ACTIVE) {
                $lease->assignRoom(
                    $data['move_in_date'] ?? $data['start_date'],
                    ($data['assignment_status'] ?? null) ?: 'active'
                );
            } else {
                $lease->tenant()->update(['status' => 'active']);
            }

            return $lease;
        });

        return $this->respond($this->present($lease), 'Lease saved.', 201);
    }

    public function show(Lease $lease): JsonResponse
    {
        return $this->respond($this->present($lease));
    }

    public function update(Request $request, Lease $lease): JsonResponse
    {
        $data = $request->validate([
            'lease_number' => ['required', 'string', 'max:50', Rule::unique('leases', 'lease_number')->ignore($lease->id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'monthly_rent' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'deposit_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $earliestMoveIn = $lease->assignments()->min('move_in_date');

        if ($earliestMoveIn && $start->gt(Carbon::parse($earliestMoveIn)->startOfDay())) {
            throw ValidationException::withMessages([
                'start_date' => 'Start date cannot be later than an existing move-in date.',
            ]);
        }

        if (! empty($data['end_date'])) {
            $latestMoveIn = $lease->assignments()->max('move_in_date');

            if ($latestMoveIn && Carbon::parse($data['end_date'])->startOfDay()->lt(Carbon::parse($latestMoveIn)->startOfDay())) {
                throw ValidationException::withMessages([
                    'end_date' => 'End date cannot be earlier than an existing move-in date.',
                ]);
            }
        }

        $lease->update($data);

        return $this->respond($this->present($lease), 'Lease updated. Existing bills keep their original amounts.');
    }

    public function destroy(Lease $lease): JsonResponse
    {
        if ($lease->billingRecords()->exists() || $lease->assignments()->exists()) {
            throw ValidationException::withMessages([
                'lease' => 'This lease has assignment or billing history and cannot be deleted.',
            ]);
        }

        $lease->delete();

        return response()->json([
            'message' => 'Draft lease deleted.',
            'deleted' => true,
        ]);
    }

    public function allocate(Request $request, Lease $lease): JsonResponse
    {
        $data = $request->validate([
            'move_in_date' => ['required', 'date'],
            'assignment_status' => ['nullable', 'in:active,reserved'],
        ]);

        DB::transaction(function () use ($lease, $data) {
            $locked = Lease::query()->whereKey($lease->id)->lockForUpdate()->firstOrFail();
            $locked->assignRoom($data['move_in_date'], $data['assignment_status'] ?? 'active');
        });

        return $this->respond($this->present($lease), 'Tenant allocated to the lease room.', 201);
    }

    public function terminate(Request $request, Lease $lease): JsonResponse
    {
        $data = $request->validate([
            'end_date' => ['nullable', 'date'],
            'move_out_date' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($lease, $data) {
            $locked = Lease::query()->whereKey($lease->id)->lockForUpdate()->firstOrFail();
            $locked->terminate($data['end_date'] ?? null, $data['move_out_date'] ?? null);
        });

        return $this->respond($this->present($lease), 'Lease terminated. Occupancy history was kept.');
    }

    public function expire(Request $request, Lease $lease): JsonResponse
    {
        $data = $request->validate([
            'move_out_date' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($lease, $data) {
            $locked = Lease::query()->whereKey($lease->id)->lockForUpdate()->firstOrFail();
            $locked->expire($data['move_out_date'] ?? null);
        });

        return $this->respond($this->present($lease), 'Lease marked expired. Occupancy history was kept.');
    }

    public function cancel(Lease $lease): JsonResponse
    {
        DB::transaction(function () use ($lease) {
            $locked = Lease::query()->whereKey($lease->id)->lockForUpdate()->firstOrFail();
            $locked->cancel();
        });

        return $this->respond($this->present($lease), 'Lease cancelled.');
    }

    private function present(Lease $lease): Lease
    {
        return Lease::query()
            ->with([
                'tenant',
                'room.property',
                'assignments' => fn ($query) => $query->with('room')->orderByDesc('move_in_date')->orderByDesc('id'),
                'billingRecords' => fn ($query) => $query->withPaidTotal()->orderByDesc('billing_date')->orderByDesc('id'),
            ])
            ->findOrFail($lease->id);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'lease_number' => ['nullable', 'string', 'max:50', Rule::unique('leases', 'lease_number')],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'monthly_rent' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'in:draft,active'],
            'move_in_date' => ['nullable', 'date', 'required_if:status,active', 'after_or_equal:start_date'],
            'assignment_status' => ['nullable', 'in:active,reserved'],
        ]);
    }
}
