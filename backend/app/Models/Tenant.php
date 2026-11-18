<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'contact_number',
        'address',
        'emergency_contact_name',
        'emergency_contact_number',
        'status',
    ];

    protected $appends = [
        'full_name',
    ];

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])));
    }

    public function syncTenancyStatus(): void
    {
        $open = $this->leases()
            ->whereIn('status', [Lease::STATUS_ACTIVE, Lease::STATUS_DRAFT])
            ->exists();

        $next = $open ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;

        if ($this->status !== $next) {
            $this->status = $next;
            $this->save();
        }
    }
}
