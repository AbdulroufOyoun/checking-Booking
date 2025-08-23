<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guest_classification_feature extends Model
{
    use HasFactory;
    protected $table = 'guest_classification_features';

    function guest_classification(): BelongsTo
    {
        return $this->belongsTo(Guest_classification::class, 'guest_classification_id');
    }

    function guest_feature(): BelongsTo
    {
        return $this->belongsTo(Guest_feature::class, 'guest_feature_id');
    }
}
