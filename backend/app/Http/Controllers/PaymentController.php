<?php

namespace App\Http\Controllers;

use App\Models\BillingRecord;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'billing_record_id' => ['nullable', 'integer', 'exists:billing_records,id'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'payment_method' => ['nullable', 'in:cash,gcash,bank_transfer,other'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Payment::query()
            ->with(['billingRecord.lease.tenant', 'billingRecord.lease.room'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        if ($request->filled('billing_record_id')) {
            $query->where('billing_record_id', $request->integer('billing_record_id'));
        }

        if ($request->filled('tenant_id')) {
            $query->whereHas('billingRecord.lease', fn ($lease) => $lease->where('tenant_id', $request->integer('tenant_id')));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->string('payment_method')->toString());
        }

        if ($request->filled('from')) {
            $query->whereDate('payment_date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('payment_date', '<=', $request->date('to'));
        }

        if ($request->boolean('all')) {
            return $this->respond($query->limit(500)->get());
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function store(Request $request, BillingRecord $billingRecord): JsonResponse
    {
        $data = $request->validate([
            'payment_reference' => ['nullable', 'string', 'max:100', Rule::unique('payments', 'payment_reference')],
            'payment_date' => ['required', 'date'],
            'amount_paid' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'payment_method' => ['required', 'in:cash,gcash,bank_transfer,other'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($billingRecord, $data) {
                $bill = BillingRecord::query()->whereKey($billingRecord->id)->lockForUpdate()->firstOrFail();

                if ($bill->status === BillingRecord::STATUS_CANCELLED) {
                    throw ValidationException::withMessages([
                        'amount_paid' => 'Payments cannot be recorded against a cancelled bill.',
                    ]);
                }

                $balance = $bill->lockedBalance();

                if (Money::comp($balance, '0') <= 0) {
                    throw ValidationException::withMessages([
                        'amount_paid' => 'This bill has no remaining balance.',
                    ]);
                }

                if (Money::comp($data['amount_paid'], $balance) === 1) {
                    throw ValidationException::withMessages([
                        'amount_paid' => 'Payment cannot exceed the remaining balance of '.$balance.'.',
                    ]);
                }

                Payment::create([
                    'billing_record_id' => $bill->id,
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'payment_date' => $data['payment_date'],
                    'amount_paid' => $data['amount_paid'],
                    'payment_method' => $data['payment_method'],
                    'notes' => $data['notes'] ?? null,
                ]);

                $bill->recalculateStatus();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'payment_reference' => 'This payment reference has already been used.',
            ]);
        }

        $bill = BillingRecord::query()
            ->withPaidTotal()
            ->with(['payments' => fn ($query) => $query->orderBy('payment_date')->orderBy('id'), 'lease.tenant'])
            ->findOrFail($billingRecord->id);

        return $this->respond($bill, 'Payment recorded.', 201);
    }

    public function void(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($payment, $data) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->voided_at !== null) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment is already voided.',
                ]);
            }

            $bill = BillingRecord::query()->whereKey($locked->billing_record_id)->lockForUpdate()->firstOrFail();
            $locked->voided_at = now();
            $locked->void_reason = $data['void_reason'];
            $locked->save();
            $bill->recalculateStatus();
        });

        return $this->respond(
            Payment::query()->with('billingRecord')->findOrFail($payment->id),
            'Payment voided. The original record was kept.'
        );
    }
}
