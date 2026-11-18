<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class Lease extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_TERMINATED = 'terminated';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'room_id',
        'lease_number',
        'start_date',
        'end_date',
        'monthly_rent',
        'deposit_amount',
        'terms',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'monthly_rent' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class);
    }

    public function billingRecords(): HasMany
    {
        return $this->hasMany(BillingRecord::class);
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'LSE-'.now()->format('Ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::query()->where('lease_number', $number)->exists());

        return $number;
    }

    public static function assertNoOtherActiveLease(int $tenantId, ?int $exceptId = null): void
    {
        $query = static::query()
            ->where('tenant_id', $tenantId)
            ->where('status', self::STATUS_ACTIVE);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'tenant_id' => 'This tenant already has an active lease.',
            ]);
        }
    }

    public function assignRoom(string $moveInDate, string $assignmentStatus = RoomAssignment::STATUS_ACTIVE): RoomAssignment
    {
        $this->guardTransaction();

        if (! in_array($assignmentStatus, [RoomAssignment::STATUS_ACTIVE, RoomAssignment::STATUS_RESERVED], true)) {
            throw ValidationException::withMessages([
                'assignment_status' => 'Assignment status must be active or reserved.',
            ]);
        }

        if (! in_array($this->status, [self::STATUS_DRAFT, self::STATUS_ACTIVE], true)) {
            throw ValidationException::withMessages([
                'lease' => 'Only a draft or active lease can be allocated.',
            ]);
        }

        Tenant::query()->whereKey($this->tenant_id)->lockForUpdate()->firstOrFail();

        if ($this->assignments()->whereIn('status', [RoomAssignment::STATUS_ACTIVE, RoomAssignment::STATUS_RESERVED])->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'lease' => 'This lease already has an open room assignment.',
            ]);
        }

        self::assertNoOtherActiveLease($this->tenant_id, $this->id);

        $moveIn = Carbon::parse($moveInDate)->startOfDay();
        $start = Carbon::parse($this->start_date)->startOfDay();

        if ($moveIn->lt($start)) {
            throw ValidationException::withMessages([
                'move_in_date' => 'Move-in date cannot be earlier than the lease start date.',
            ]);
        }

        if ($this->end_date && $moveIn->gt(Carbon::parse($this->end_date)->startOfDay())) {
            throw ValidationException::withMessages([
                'move_in_date' => 'Move-in date cannot be later than the lease end date.',
            ]);
        }

        $room = Room::query()->findOrFail($this->room_id);
        $room->assertSpaceAvailable();

        $assignment = $this->assignments()->create([
            'room_id' => $this->room_id,
            'move_in_date' => $moveIn->toDateString(),
            'move_out_date' => null,
            'status' => $assignmentStatus,
        ]);

        $this->status = self::STATUS_ACTIVE;
        $this->save();

        Tenant::query()->whereKey($this->tenant_id)->update(['status' => Tenant::STATUS_ACTIVE]);

        return $assignment;
    }

    public function terminate(?string $endDate = null, ?string $moveOutDate = null, bool $syncTenant = true): void
    {
        $this->guardTransaction();

        if (! in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_EXPIRED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only an active or expired lease can be terminated.',
            ]);
        }

        $end = Carbon::parse($endDate ?? now()->toDateString())->startOfDay();

        if ($end->lt(Carbon::parse($this->start_date)->startOfDay())) {
            throw ValidationException::withMessages([
                'end_date' => 'End date cannot be earlier than the lease start date.',
            ]);
        }

        $this->completeOpenAssignments(Carbon::parse($moveOutDate ?? $end->toDateString())->startOfDay());
        $this->end_date = $end->toDateString();
        $this->status = self::STATUS_TERMINATED;
        $this->save();

        if ($syncTenant) {
            $this->tenant()->first()?->syncTenancyStatus();
        }
    }

    public function expire(?string $moveOutDate = null): void
    {
        $this->guardTransaction();

        if ($this->status !== self::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => 'Only an active lease can be marked expired.',
            ]);
        }

        $moveOut = Carbon::parse($moveOutDate ?? now()->toDateString())->startOfDay();

        if ($this->end_date === null || Carbon::parse($this->end_date)->startOfDay()->gt($moveOut)) {
            $this->end_date = $moveOut->toDateString();
        }

        $this->completeOpenAssignments($moveOut);
        $this->status = self::STATUS_EXPIRED;
        $this->save();
        $this->tenant()->first()?->syncTenancyStatus();
    }

    public function cancel(): void
    {
        $this->guardTransaction();

        if ($this->status !== self::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only a draft lease can be cancelled.',
            ]);
        }

        if ($this->billingRecords()->exists() || $this->assignments()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'This lease has history and cannot be cancelled. Terminate it instead.',
            ]);
        }

        $this->status = self::STATUS_CANCELLED;
        $this->save();
        $this->tenant()->first()?->syncTenancyStatus();
    }

    private function completeOpenAssignments(Carbon $moveOut): void
    {
        $open = $this->assignments()
            ->whereIn('status', [RoomAssignment::STATUS_ACTIVE, RoomAssignment::STATUS_RESERVED])
            ->lockForUpdate()
            ->get();

        foreach ($open as $assignment) {
            if ($assignment->status === RoomAssignment::STATUS_RESERVED) {
                $assignment->status = RoomAssignment::STATUS_CANCELLED;
                $assignment->save();

                continue;
            }

            $moveIn = Carbon::parse($assignment->move_in_date)->startOfDay();
            $out = $moveOut->lt($moveIn) ? $moveIn : $moveOut;
            $assignment->status = RoomAssignment::STATUS_COMPLETED;
            $assignment->move_out_date = $out->toDateString();
            $assignment->save();
        }
    }

    private function guardTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Lease occupancy changes must run inside a database transaction.');
        }
    }
}
