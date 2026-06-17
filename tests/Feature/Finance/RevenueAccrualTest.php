<?php

namespace Tests\Feature\Finance;

use App\Models\Reservation;
use App\Models\ReservationDailyCharge;
use App\Services\RevenueAccrualService;
use Carbon\Carbon;
use Database\Seeders\ReservationTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueAccrualTest extends TestCase
{
    public function test_august_revenue_uses_daily_charges_not_flat_rate(): void
    {
        $this->artisan('db:seed', ['--class' => ReservationTestDataSeeder::class, '--force' => true, '--no-interaction' => true]);

        $service = app(RevenueAccrualService::class);
        $aug = $service->calculate('total', null, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), true);

        $this->assertGreaterThan(0, $aug['current']['count']);
        $this->assertGreaterThan(0, $aug['current']['total']);

        $booking6 = Reservation::where('start_date', '2026-07-26')->first();
        $this->assertNotNull($booking6);

        $augNights = ReservationDailyCharge::where('reservation_id', $booking6->id)
            ->whereBetween('charge_date', ['2026-08-01', '2026-08-31'])
            ->count();

        $this->assertEquals(8, $augNights);
        $this->assertEquals(
            round($aug['current']['total_base'], 2),
            round(ReservationDailyCharge::whereBetween('charge_date', ['2026-08-01', '2026-08-31'])
                ->whereHas('reservation', fn ($q) => $q->where('reservation_status', 1))
                ->sum('base_amount'), 2)
        );
    }

    public function test_status_two_excluded_from_revenue(): void
    {
        $this->artisan('db:seed', ['--class' => ReservationTestDataSeeder::class, '--force' => true, '--no-interaction' => true]);

        $service = app(RevenueAccrualService::class);
        $jun = $service->calculate('total', null, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'), true);

        $status2Ids = Reservation::where('reservation_status', 2)->pluck('id');
        $detailIds = collect($jun['details'] ?? [])->pluck('reservation_id')->unique();

        $this->assertTrue($detailIds->intersect($status2Ids)->isEmpty());
    }

    public function test_reservation_base_matches_sum_of_daily_charges(): void
    {
        $this->artisan('db:seed', ['--class' => ReservationTestDataSeeder::class, '--force' => true, '--no-interaction' => true]);

        foreach (Reservation::whereYear('start_date', 2026)->get() as $reservation) {
            $sum = (float) ReservationDailyCharge::where('reservation_id', $reservation->id)->sum('base_amount');
            $this->assertEqualsWithDelta(
                (float) $reservation->base_price,
                $sum,
                0.02,
                "Reservation {$reservation->id} base mismatch"
            );
        }
    }
}
