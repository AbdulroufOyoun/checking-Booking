<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'days_before_checkin',
        'refund_percent',
        'payment_status',
        'during_stay',
    ];

    protected $casts = [
        'refund_percent' => 'decimal:2',
    ];

    // Scopes
    public function scopePreCheckin($query)
    {
        return $query->where('during_stay', 0);
    }

    public function scopeDuringStay($query)
    {
        return $query->where('during_stay', 1);
    }

    public function scopePaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }
}

