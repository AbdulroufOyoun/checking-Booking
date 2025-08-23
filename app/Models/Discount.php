<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'is_percentage', 'percent', 'is_fixed', 'fixed_amount', 'is_active'];

    function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
    function guest_classification(): HasMany
    {
        return $this->hasMany(related: Guest_classification::class);
    }
}
