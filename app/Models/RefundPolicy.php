<?php

namespace App\Models;

use App\Support\RefundPolicyPaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'rent_type',
        'timing',
        'days_threshold',
        'refund_basis',
        'days_before_checkin',
        'refund_percent',
        'payment_status',
        'payment_statuses',
        'during_stay',
    ];

    protected $casts = [
        'refund_percent' => 'decimal:2',
        'payment_statuses' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (RefundPolicy $policy) {
            if ($policy->isDirty('payment_statuses')) {
                $normalized = RefundPolicyPaymentStatus::normalize($policy->payment_statuses);
                $policy->payment_statuses = $normalized === [] ? null : $normalized;
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public function resolvedPaymentStatuses(): array
    {
        $statuses = RefundPolicyPaymentStatus::normalize($this->payment_statuses);

        if ($statuses !== []) {
            return $statuses;
        }

        if ($this->payment_status === null) {
            return [];
        }

        return match ((int) $this->payment_status) {
            1 => [RefundPolicyPaymentStatus::PARTIAL],
            2 => [RefundPolicyPaymentStatus::FULL],
            default => [],
        };
    }

    public function matchesPaymentContext(array $context): bool
    {
        return RefundPolicyPaymentStatus::matches(
            $this->resolvedPaymentStatuses(),
            (int) $context['payment_status'],
            (float) $context['net_paid']
        );
    }

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

