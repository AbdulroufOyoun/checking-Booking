<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Client;

class Reservation extends Model
{
    use HasFactory;

    public $timestamps = true;


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
