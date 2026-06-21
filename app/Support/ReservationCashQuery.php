<?php

namespace App\Support;

use App\Models\Reservation;
use App\Models\ReservationPay;
use Illuminate\Database\Eloquent\Builder;

class ReservationCashQuery
{
    /** Statuses included when listing payment/refund cash movements. */
    public static function cashMovementStatuses(): array
    {
        return [
            Reservation::STATUS_CONFIRMED,
            Reservation::STATUS_PENDING_PAYMENT,
            Reservation::STATUS_CANCELLED,
        ];
    }

    public static function applyCashMovementJoin(Builder $query): Builder
    {
        return $query->join('reservations', 'reservation_pay.reservation_id', '=', 'reservations.id')
            ->whereIn('reservations.reservation_status', self::cashMovementStatuses());
    }

    public static function paymentQuery(): Builder
    {
        return ReservationPay::query()
            ->tap(fn (Builder $q) => self::applyCashMovementJoin($q))
            ->where('reservation_pay.type', ReservationPay::TYPE_PAYMENT);
    }

    public static function refundQuery(): Builder
    {
        return ReservationPay::query()
            ->tap(fn (Builder $q) => self::applyCashMovementJoin($q))
            ->where('reservation_pay.type', ReservationPay::TYPE_REFUND);
    }
}
