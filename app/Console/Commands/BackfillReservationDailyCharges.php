<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\PricingEngine;
use App\Services\RevenueAccrualService;
use Illuminate\Console\Command;

class BackfillReservationDailyCharges extends Command
{
    protected $signature = 'reservations:backfill-daily-charges
                            {--reservation_id=}
                            {--sync-base : Recalculate base_price/subtotal/taxes/total from daily lines}
                            {--status=1 : Only reservations with this status (use * for all)}';

    protected $description = 'Generate reservation_daily_charges for existing reservations';

    public function handle(PricingEngine $pricingEngine, RevenueAccrualService $revenueAccrualService): int
    {
        $query = Reservation::with(['reservationRooms.room']);

        if ($id = $this->option('reservation_id')) {
            $query->where('id', $id);
        }

        $status = $this->option('status');
        if ($status !== '*') {
            $query->where('reservation_status', (int) $status);
        }

        $syncBase = (bool) $this->option('sync-base');
        $count = 0;
        $mismatch = 0;

        $query->orderBy('id')->chunk(50, function ($reservations) use (
            $pricingEngine,
            $revenueAccrualService,
            $syncBase,
            &$count,
            &$mismatch
        ) {
            foreach ($reservations as $reservation) {
                $priceMode = 0;
                $totalBase = 0.0;

                foreach ($reservation->reservationRooms as $resRoom) {
                    if (!$resRoom->room) {
                        continue;
                    }

                    $lines = $pricingEngine->buildDailyBreakdown(
                        $resRoom->room,
                        $reservation->start_date,
                        $reservation->expire_date,
                        (int) $reservation->rent_type,
                        $priceMode
                    );

                    $revenueAccrualService->persistDailyCharges(
                        $reservation->id,
                        $resRoom->id,
                        $resRoom->room_id,
                        (int) $reservation->rent_type,
                        $lines
                    );

                    $totalBase += $pricingEngine->sumBaseAmount($lines);
                }

                if ($syncBase && $totalBase > 0) {
                    $subtotal = $totalBase
                        - (float) $reservation->discount
                        + (float) $reservation->extras
                        + (float) $reservation->penalties;
                    $taxes = round($subtotal * 0.15, 2, PHP_ROUND_HALF_UP);
                    $total = round($subtotal + $taxes, 2);

                    if (abs((float) $reservation->base_price - $totalBase) >= 0.02) {
                        $mismatch++;
                    }

                    $reservation->update([
                        'base_price' => round($totalBase, 2),
                        'subtotal'   => round($subtotal, 2),
                        'taxes'      => $taxes,
                        'total'      => $total,
                    ]);
                }

                $count++;
            }
        });

        $this->info("Backfilled daily charges for {$count} reservation(s).");
        if ($syncBase) {
            $this->info("Base price synced on {$count} reservation(s); {$mismatch} had prior base mismatch.");
        }

        return self::SUCCESS;
    }
}
