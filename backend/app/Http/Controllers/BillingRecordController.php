<?php

namespace App\Http\Controllers;

use App\Models\BillingRecord;
use App\Models\Lease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingRecordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:unpaid,partially_paid,paid,overdue,cancelled'],
            'lease_id' => ['nullable', 'integer', 'exists:leases,id'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date'],
        ]);

        $query = BillingRecord::query()
            ->withPaidTotal()
            ->with(['lease.tenant', 'lease.room.property'])
            ->orderByDesc('billing_date')
            ->orderByDesc('id');

        if ($request->input('status') === 'overdue') {
            $query->whereIn('status', [BillingRecord::STATUS_UNPAID, BillingRecord::STATUS_PARTIAL])
                ->whereDate('due_date', '<', today());
        } elseif ($request->input('status') === BillingRecord::STATUS_UNPAID) {
            $query->where('status', BillingRecord::STATUS_UNPAID)
                ->whereDate('due_date', '>=', today());
        } elseif ($request->input('status') === BillingRecord::STATUS_PARTIAL) {
            $query->where('status', BillingRecord::STATUS_PARTIAL)
                ->whereDate('due_date', '>=', today());
        } elseif ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('lease_id')) {
            $query->where('lease_id', $request->integer('lease_id'));
        }

        if ($request->filled('tenant_id')) {
            $query->whereHas('lease', fn ($lease) => $lease->where('tenant_id', $request->integer('tenant_id')));
        }

        if ($request->filled('due_from')) {
            $query->whereDate('due_date', '>=', $request->date('due_from'));
        }

        if ($request->filled('due_to')) {
            $query->whereDate('due_date', '<=', $request->date('due_to'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($builder) use ($search) {
                $builder->where('billing_number', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        if ($request->boolean('all')) {
            return $this->respond($query->limit(500)->get());
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lease_id' => ['required', 'integer', 'exists:leases,id'],
            'billing_number' => ['nullable', 'string', 'max:50', Rule::unique('billing_records', 'billing_number')],
            'billing_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:billing_date'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
        ]);

        $bill = DB::transaction(function () use ($data) {
            $lease = Lease::query()->whereKey($data['lease_id'])->lockForUpdate()->firstOrFail();

            if (! in_array($lease->status, [Lease::STATUS_ACTIVE, Lease::STATUS_EXPIRED, Lease::STATUS_TERMINATED], true)) {
                throw ValidationException::withMessages([
                    'lease_id' => 'Bills can only be created for an active, expired, or terminated lease.',
                ]);
            }

            return BillingRecord::create([
                'lease_id' => $lease->id,
                'billing_number' => ($data['billing_number'] ?? null) ?: BillingRecord::generateNumber(),
                'billing_date' => $data['billing_date'],
                'due_date' => $data['due_date'],
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'description' => $data['description'],
                'amount' => $data['amount'],
                'status' => BillingRecord::STATUS_UNPAID,
            ]);
        });

        return $this->respond($this->present($bill), 'Billing record created.', 201);
    }

    public function show(BillingRecord $billingRecord): JsonResponse
    {
        return $this->respond($this->present($billingRecord));
    }

    public function cancel(BillingRecord $billingRecord): JsonResponse
    {
        DB::transaction(function () use ($billingRecord) {
            $locked = BillingRecord::query()->whereKey($billingRecord->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === BillingRecord::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'This bill is already cancelled.',
                ]);
            }

            $hasPayments = $locked->payments()->whereNull('voided_at')->lockForUpdate()->exists();

            if ($hasPayments) {
                throw ValidationException::withMessages([
                    'status' => 'Void the recorded payments before cancelling this bill.',
                ]);
            }

            $locked->status = BillingRecord::STATUS_CANCELLED;
            $locked->save();
        });

        return $this->respond($this->present($billingRecord), 'Billing record cancelled. It was not deleted.');
    }

    private function present(BillingRecord $billingRecord): BillingRecord
    {
        return BillingRecord::query()
            ->withPaidTotal()
            ->with([
                'lease.tenant',
                'lease.room.property',
                'payments' => fn ($query) => $query->orderBy('payment_date')->orderBy('id'),
            ])
            ->findOrFail($billingRecord->id);
    }
}
