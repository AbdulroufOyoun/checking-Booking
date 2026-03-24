<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Client;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'start_date',
        'nights',
        'expire_date',
        'reservation_type',
        'reservation_status',
        'stay_reason_id',
        'reservation_source_id',
        'rent_type',
        'price_calculation_mode',
        'base_price',
        'discount',
        'extras',
        'penalties',
        'subtotal',
        'taxes',
        'total',
        'logedin',
        'login_time',
        'user_id',
    ];

    public $timestamps = true;

    /**
     * Get the rooms associated with this reservation (from reservation_rooms table)
     */
    public function reservationRooms(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    /**
     * Get the first room of this reservation (for backward compatibility)
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    function stay_reason(): BelongsTo
    {
        return $this->belongsTo(Stay_reason::class);
    }

    function reservation_source(): BelongsTo
    {
        return $this->belongsTo(Reservation_source::class);
    }
}
