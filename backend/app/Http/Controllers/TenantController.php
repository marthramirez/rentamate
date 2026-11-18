<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:active,inactive'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Tenant::query()
            ->with(['leases' => function ($leases) {
                $leases->where('status', Lease::STATUS_ACTIVE)->with('room.property');
            }])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($builder) use ($search) {
                $builder->where('first_name', 'like', $search)
                    ->orWhere('middle_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('contact_number', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if ($request->boolean('all')) {
            return $this->respond($query->limit(500)->get());
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = Tenant::create($this->validated($request));

        return $this->respond($tenant, 'Tenant registered.', 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load([
            'leases' => fn ($query) => $query->with('room.property')->orderByDesc('start_date')->orderByDesc('id'),
            'leases.assignments' => fn ($query) => $query->with('room')->orderByDesc('move_in_date')->orderByDesc('id'),
            'leases.billingRecords' => fn ($query) => $query->withPaidTotal()->with('payments')->orderByDesc('billing_date')->orderByDesc('id'),
        ]);

        return $this->respond($tenant);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $this->validated($request);

        if ($data['status'] === Tenant::STATUS_INACTIVE && $tenant->leases()->where('status', Lease::STATUS_ACTIVE)->exists()) {
            throw ValidationException::withMessages([
                'status' => 'End the active lease before marking this tenant inactive.',
            ]);
        }

        $tenant->update($data);

        return $this->respond($tenant->fresh(), 'Tenant updated.');
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        if ($tenant->leases()->where('status', Lease::STATUS_ACTIVE)->exists()) {
            throw ValidationException::withMessages([
                'status' => 'End the active lease before deactivating this tenant.',
            ]);
        }

        if ($tenant->leases()->exists()) {
            $tenant->update(['status' => Tenant::STATUS_INACTIVE]);

            return $this->respond($tenant->fresh(), 'Tenant has history, so the record was deactivated instead of deleted.');
        }

        $tenant->delete();

        return response()->json([
            'message' => 'Tenant deleted.',
            'deleted' => true,
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_number' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $data['status'] = $data['status'] ?? Tenant::STATUS_ACTIVE;

        return $data;
    }
}
