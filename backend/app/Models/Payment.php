<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const METHOD_CASH = 'cash';

    public const METHOD_GCASH = 'gcash';

    public const METHOD_BANK = 'bank_transfer';

    public const METHOD_OTHER = 'other';

    protected $fillable = [
        'billing_record_id',
        'payment_reference',
        'payment_date',
        'amount_paid',
        'payment_method',
        'notes',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date:Y-m-d',
            'amount_paid' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public function billingRecord(): BelongsTo
    {
        return $this->belongsTo(BillingRecord::class);
    }
}
