<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class BillingRecord extends Model
{
    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIAL = 'partially_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'lease_id',
        'billing_number',
        'billing_date',
        'due_date',
        'period_start',
        'period_end',
        'description',
        'amount',
        'status',
    ];

    protected $appends = [
        'amount_paid',
        'balance',
        'computed_status',
    ];

    protected $hidden = [
        'payments_sum_amount_paid',
    ];

    protected function casts(): array
    {
        return [
            'billing_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'amount' => 'decimal:2',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeWithPaidTotal(Builder $query): Builder
    {
        return $query->withSum(['payments' => function (Builder $payments) {
            $payments->whereNull('voided_at');
        }], 'amount_paid');
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'BIL-'.now()->format('Ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::query()->where('billing_number', $number)->exists());

        return $number;
    }

    public static function totalOutstanding(): string
    {
        $paid = DB::table('payments')
            ->select('billing_record_id', DB::raw('SUM(amount_paid) as paid'))
            ->whereNull('voided_at')
            ->groupBy('billing_record_id');

        $total = DB::table('billing_records')
            ->leftJoinSub($paid, 'paid_sums', 'paid_sums.billing_record_id', '=', 'billing_records.id')
            ->where('billing_records.status', '!=', self::STATUS_CANCELLED)
            ->selectRaw('COALESCE(SUM(billing_records.amount - COALESCE(paid_sums.paid, 0)), 0) as total')
            ->value('total');

        return Money::of($total);
    }

    public function getAmountPaidAttribute(): string
    {
        if (array_key_exists('payments_sum_amount_paid', $this->attributes)) {
            return Money::of($this->attributes['payments_sum_amount_paid']);
        }

        return Money::of($this->payments()->whereNull('voided_at')->sum('amount_paid'));
    }

    public function getBalanceAttribute(): string
    {
        return Money::sub($this->amount, $this->amount_paid);
    }

    public function getComputedStatusAttribute(): string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return self::STATUS_CANCELLED;
        }

        if (Money::comp($this->balance, '0') <= 0) {
            return self::STATUS_PAID;
        }

        if ($this->due_date !== null && $this->due_date->copy()->startOfDay()->lt(today())) {
            return 'overdue';
        }

        if (Money::comp($this->amount_paid, '0') === 1) {
            return self::STATUS_PARTIAL;
        }

        return self::STATUS_UNPAID;
    }

    public function lockedBalance(): string
    {
        $paid = $this->payments()->whereNull('voided_at')->lockForUpdate()->sum('amount_paid');

        return Money::sub($this->amount, $paid);
    }

    public function recalculateStatus(): void
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }

        $paid = Money::of($this->payments()->whereNull('voided_at')->sum('amount_paid'));
        $balance = Money::sub($this->amount, $paid);

        if (Money::comp($balance, '0') <= 0) {
            $this->status = self::STATUS_PAID;
        } elseif (Money::comp($paid, '0') === 1) {
            $this->status = self::STATUS_PARTIAL;
        } else {
            $this->status = self::STATUS_UNPAID;
        }

        $this->save();
    }
}
