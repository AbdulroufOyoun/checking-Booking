<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use function PHPUnit\Framework\isFalse;

class Room extends Model
{
    use HasFactory;
    public $timestamps = true;

    function suite(): BelongsTo
    {
        return $this->belongsTo(Suite::class);
    }

    function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    function roomFeatures(): HasMany
    {
        return $this->hasMany(Room_feature::class);
    }

    function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    function isHasReservation()
    {
        $res = Reservation::where('room_id', $this->id)->exists();
        return $res;
    }
}
