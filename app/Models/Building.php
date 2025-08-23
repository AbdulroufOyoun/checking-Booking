<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    use HasFactory;

    public $timestamps = true;

    function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }

    function suites(): HasMany
    {
        return $this->hasMany(Suite::class);
    }

    function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    function facilitie(): HasMany
    {
        return $this->hasMany(Facilitie::class);
    }
}
