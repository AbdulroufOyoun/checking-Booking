<?php

namespace App\Services;

use App\Exceptions\RefundNotAllowedException;
use App\Models\RefundPolicy;
use App\Models\Reservation;
use App\Models\ReservationDailyCharge;
use Carbon\Carbon;

class RefundPolicyService
{
    public const TAX_RATE = 0.15;

    /**
     * @return array<string, mixed>
     */
    public function preview(Reservation $reservation): array
    {
        $reservation->loadMissing(['payments', 'client']);

        $this->assertRefundable($reservation);

        $context = $this->buildContext($reservation);
        $policy = $this->resolvePolicy($reservation, $context);

        if (!$policy) {
            throw new RefundNotAllowedException(
                'No refund policy applies to this reservation.'
            );
        }

        $amount = $this->calculateRefundAmount($reservation, $policy, $context);

        if ($amount <= 0) {
            throw new RefundNotAllowedException(
                'Based on cancellation policy, no refund amount due at this time.'
            );
        }

        return [
            'policy' => $policy,
            'refund_amount' => $amount,
            'breakdown' => array_merge($context, [
                'policy_id' => $policy->id,
                'policy_name' => $policy->name,
                'refund_percent' => (float) $policy->refund_percent,
                'refund_basis' => $policy->refund_basis ?? 'total',
            ]),
        ];
    }

    public function assertRefundable(Reservation $reservation): void
    {
        if (Reservation::isCancelled((int) $reservation->reservation_status)) {
            throw new RefundNotAllowedException('Reservation already cancelled, cannot refund.');
        }

        if ($reservation->expire_date < Carbon::today()->toDateString()) {
            throw new RefundNotAllowedException('Cannot refund a completed stay.');
        }

        if ($this->netPaid($reservation) <= 0) {
            throw new RefundNotAllowedException('No payments available for refund.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContext(Reservation $reservation): array
    {
        $now = Carbon::now();
        $start = Carbon::parse($reservation->start_date)->startOfDay();
        $expire = Carbon::parse($reservation->expire_date)->startOfDay();

        $daysUntilStart = $now->lt($start) ? (int) $now->diffInDays($start, false) : 0;
        $daysSinceStart = $now->gte($start) ? (int) $start->diffInDays($now, false) : 0;
        $duringStay = $now->betweenIncluded($start, $expire);

        $netPaid = $this->netPaid($reservation);
        $total = (float) $reservation->total;
        $paymentStatus = $netPaid <= 0 ? 0 : ($netPaid >= $total ? 2 : 1);

        return [
            'net_paid' => round($netPaid, 2),
            'total' => round($total, 2),
            'payment_status' => $paymentStatus,
            'rent_type' => (int) $reservation->rent_type,
            'days_until_start' => $daysUntilStart,
            'days_since_start' => $daysSinceStart,
            'during_stay' => $duringStay,
            'remaining_base' => round($this->remainingNightsBase($reservation), 2),
            'tax_rate' => self::TAX_RATE,
        ];
    }

    public function resolvePolicy(Reservation $reservation, ?array $context = null): ?RefundPolicy
    {
        $context ??= $this->buildContext($reservation);

        $candidates = RefundPolicy::query()
            ->where(function ($q) use ($reservation) {
                $q->whereNull('rent_type')
                    ->orWhere('rent_type', (int) $reservation->rent_type);
            })
            ->get();

        $matched = $candidates->filter(function (RefundPolicy $policy) use ($context) {
            if (!$policy->matchesPaymentContext($context)) {
                return false;
            }

            $timing = $policy->timing ?? ((int) $policy->during_stay === 1 ? 'after_start' : 'before_start');
            $threshold = (int) ($policy->days_threshold ?? $policy->days_before_checkin ?? 0);

            if ($timing === 'before_start') {
                return !$context['during_stay'] && $context['days_until_start'] >= $threshold;
            }

            return $context['during_stay'] && $context['days_since_start'] >= $threshold;
        });

        // Most specific rule wins (highest days threshold that still matches).
        return $matched->sortByDesc(
            fn (RefundPolicy $p) => (int) ($p->days_threshold ?? $p->days_before_checkin ?? 0)
        )->first();
    }

    public function calculateRefundAmount(
        Reservation $reservation,
        RefundPolicy $policy,
        ?array $context = null
    ): float {
        $context ??= $this->buildContext($reservation);
        $netPaid = $context['net_paid'];
        $basis = $policy->refund_basis ?? 'total';
        $percent = (float) $policy->refund_percent;

        $raw = match ($basis) {
            'remaining_nights' => ($context['remaining_base'] * (1 + self::TAX_RATE)) * ($percent / 100),
            'paid_net' => min($netPaid, ((float) $reservation->total) * ($percent / 100)),
            default => ((float) $reservation->total) * ($percent / 100),
        };

        return round(min($raw, $netPaid), 2);
    }

    public function netPaid(Reservation $reservation): float
    {
        return $reservation->paidNetAmount();
    }

    private function remainingNightsBase(Reservation $reservation): float
    {
        $today = Carbon::today()->toDateString();

        return (float) ReservationDailyCharge::query()
            ->where('reservation_id', $reservation->id)
            ->where('charge_date', '>=', $today)
            ->sum('base_amount');
    }
}
