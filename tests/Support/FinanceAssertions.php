<?php

namespace Tests\Support;

use App\Models\Reservation;
use App\Models\ReservationDailyCharge;
use App\Services\PricingEngine;
use PHPUnit\Framework\Assert;

trait FinanceAssertions
{
    protected const TAX_RATE = 0.15;

    protected function assertReservationFinancialContract(Reservation $reservation, string $context = ''): void
    {
        $prefix = $context ? "{$context}: " : '';

        $base = (float) $reservation->base_price;
        $discount = (float) $reservation->discount;
        $extras = (float) $reservation->extras;
        $penalties = (float) $reservation->penalties;
        $subtotal = (float) $reservation->subtotal;
        $taxes = (float) $reservation->taxes;
        $total = (float) $reservation->total;

        $expectedSubtotal = round($base - $discount + $extras + $penalties, 2);
        Assert::assertEqualsWithDelta(
            $expectedSubtotal,
            $subtotal,
            0.02,
            $prefix . 'subtotal = base - discount + extras + penalties'
        );

        $expectedTaxes = round($subtotal * self::TAX_RATE, 2, PHP_ROUND_HALF_UP);
        Assert::assertEqualsWithDelta($expectedTaxes, $taxes, 0.02, $prefix . 'taxes = 15% of subtotal');

        Assert::assertEqualsWithDelta($subtotal + $taxes, $total, 0.02, $prefix . 'total = subtotal + taxes');

        $chargeSum = (float) ReservationDailyCharge::where('reservation_id', $reservation->id)->sum('base_amount');
        Assert::assertEqualsWithDelta($base, $chargeSum, 0.02, $prefix . 'base_price = sum(daily charges)');
    }

    protected function assertPricingEngineMatchesReservation(
        PricingEngine $engine,
        Reservation $reservation
    ): void {
        foreach ($reservation->reservationRooms as $resRoom) {
            if (!$resRoom->room) {
                continue;
            }

            $lines = $engine->buildDailyBreakdown(
                $resRoom->room,
                $reservation->start_date,
                $reservation->expire_date,
                (int) $reservation->rent_type,
                (int) ($reservation->price_calculation_mode ?? 0)
            );

            $stored = (float) ReservationDailyCharge::where('reservation_room_id', $resRoom->id)->sum('base_amount');
            Assert::assertEqualsWithDelta(
                $engine->sumBaseAmount($lines),
                $stored,
                0.05,
                "Repricing reservation {$reservation->id} room {$resRoom->id}"
            );
        }
    }

    /**
     * @param  array{summary?: array<int, array{label: string, value: mixed}>}  $reportData
     */
    protected function reportSummaryValue(array $reportData, string $label): ?float
    {
        foreach ($reportData['summary'] ?? [] as $item) {
            if (($item['label'] ?? '') === $label) {
                return is_numeric($item['value']) ? (float) $item['value'] : null;
            }
        }

        return null;
    }
}
