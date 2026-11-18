<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RoomAssignment extends Model
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'lease_id',
        'room_id',
        'move_in_date',
        'move_out_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'move_in_date' => 'date:Y-m-d',
            'move_out_date' => 'date:Y-m-d',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function moveOut(string $moveOutDate, bool $terminateLease = true): void
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Move-out must run inside a database transaction.');
        }

        if (! in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_RESERVED], true)) {
            throw ValidationException::withMessages([
                'assignment' => 'Only an open assignment can be closed.',
            ]);
        }

        $out = Carbon::parse($moveOutDate)->startOfDay();
        $in = Carbon::parse($this->move_in_date)->startOfDay();

        if ($out->lt($in)) {
            throw ValidationException::withMessages([
                'move_out_date' => 'Move-out date cannot be earlier than the move-in date.',
            ]);
        }

        if ($this->status === self::STATUS_RESERVED) {
            $this->status = self::STATUS_CANCELLED;
        } else {
            $this->status = self::STATUS_COMPLETED;
            $this->move_out_date = $out->toDateString();
        }

        $this->save();

        if (! $terminateLease) {
            return;
        }

        $lease = $this->lease()->lockForUpdate()->first();

        if ($lease && in_array($lease->status, [Lease::STATUS_ACTIVE, Lease::STATUS_EXPIRED], true)) {
            $lease->terminate($out->toDateString(), $out->toDateString());
        }
    }
}
