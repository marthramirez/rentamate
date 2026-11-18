<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'address',
        'contact_number',
        'description',
        'status',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class)->orderBy('room_number');
    }
}
