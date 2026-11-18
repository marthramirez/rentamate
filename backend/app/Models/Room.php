<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class Room extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'property_id',
        'room_number',
        'floor',
        'capacity',
        'monthly_rate',
        'description',
        'status',
    ];

    protected $appends = [
        'available_spaces',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'monthly_rate' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class);
    }

    public function scopeWithOccupancy(Builder $query): Builder
    {
        return $query->withCount([
            'assignments as active_occupancy' => fn (Builder $assignments) => $assignments->where('status', RoomAssignment::STATUS_ACTIVE),
            'assignments as reserved_count' => fn (Builder $assignments) => $assignments->where('status', RoomAssignment::STATUS_RESERVED),
        ]);
    }

    public function getAvailableSpacesAttribute(): int
    {
        if (! array_key_exists('active_occupancy', $this->attributes)) {
            $active = $this->assignments()->where('status', RoomAssignment::STATUS_ACTIVE)->count();
            $reserved = $this->assignments()->where('status', RoomAssignment::STATUS_RESERVED)->count();

            return max(0, (int) $this->capacity - $active - $reserved);
        }

        $active = (int) $this->attributes['active_occupancy'];
        $reserved = (int) ($this->attributes['reserved_count'] ?? 0);

        return max(0, (int) $this->capacity - $active - $reserved);
    }

    /**
     * Active stays and reservations both consume capacity.
     * The room row is locked so two allocations cannot pass this check together.
     */
    public function assertSpaceAvailable(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Room capacity must be checked inside a database transaction.');
        }

        $locked = static::query()->whereKey($this->id)->lockForUpdate()->first();

        if ($locked === null) {
            throw ValidationException::withMessages([
                'room_id' => 'The selected room does not exist.',
            ]);
        }

        $this->setRawAttributes($locked->getAttributes(), true);
        $this->exists = true;

        if ($this->status !== self::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'room_id' => 'Only active rooms can receive a new allocation.',
            ]);
        }

        $property = Property::query()->whereKey($this->property_id)->lockForUpdate()->first();

        if ($property === null || $property->status !== Property::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'room_id' => 'This property is inactive.',
            ]);
        }

        $taken = RoomAssignment::query()
            ->where('room_id', $this->id)
            ->whereIn('status', [RoomAssignment::STATUS_ACTIVE, RoomAssignment::STATUS_RESERVED])
            ->count();

        if ($taken >= (int) $this->capacity) {
            throw ValidationException::withMessages([
                'room_id' => 'This room has no available space.',
            ]);
        }
    }
}
