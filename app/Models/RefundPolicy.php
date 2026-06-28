<?php

namespace App\Models;

use App\Support\RefundPolicyPaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundPolicy extends Model
{
    use HasFactory;

    public const THRESHOLD_FIXED_DAYS = 'fixed_days';

    public const THRESHOLD_PERCENT_OF_STAY = 'percent_of_stay';

    protected $fillable = [
        'name',
        'rent_type',
        'timing',
        'threshold_mode',
        'threshold_percent',
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
        'threshold_percent' => 'decimal:2',
        'payment_statuses' => 'array',
    ];

    public function resolveThresholdDays(int $totalNights): int
    {
        $mode = $this->threshold_mode ?? self::THRESHOLD_FIXED_DAYS;

        if ($mode === self::THRESHOLD_PERCENT_OF_STAY) {
            $percent = (float) ($this->threshold_percent ?? 0);

            return (int) ceil($totalNights * $percent / 100);
        }

        return (int) ($this->days_threshold ?? $this->days_before_checkin ?? 0);
    }

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

