<?php

namespace Tests\Feature\Reservation;

use App\Models\Reservation;
use App\Models\ReservationPay;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ReservationTestDataSeeder;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    public function test_payment_appears_in_earnings_summary(): void
    {
        $this->artisan('db:seed', [
            '--class' => ReservationTestDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $user = $this->userWithApiPermissions();
        $reservation = Reservation::where('reservation_status', 1)
            ->whereDoesntHave('payments')
            ->first();

        if (!$reservation) {
            $reservation = Reservation::where('reservation_status', 1)->first();
        }

        $this->assertNotNull($user);
        $this->assertNotNull($reservation);

        $amount = 250.50;
        $payDate = '2026-08-15';

        Carbon::setTestNow($payDate);

        $before = $this->actingAs($user, 'api')->getJson(
            '/api/users/earnings-summary?start_date=2026-08-01&end_date=2026-08-31'
        );
        $before->assertStatus(200);
        $totalBefore = (float) ($before->json('data.total_in') ?? 0);

        $payResponse = $this->actingAs($user, 'api')->postJson(
            "/api/users/reservations/{$reservation->id}/payments",
            ['pay' => $amount, 'type' => 0]
        );
        $payResponse->assertStatus(200);
        $payResponse->assertJsonPath('success', true);

        $this->assertDatabaseHas('reservation_pay', [
            'reservation_id' => $reservation->id,
            'pay' => $amount,
        ]);

        $after = $this->actingAs($user, 'api')->getJson(
            '/api/users/earnings-summary?start_date=2026-08-01&end_date=2026-08-31'
        );
        $after->assertStatus(200);

        $totalAfter = (float) ($after->json('data.total_in') ?? 0);
        $this->assertEqualsWithDelta($totalBefore + $amount, $totalAfter, 0.02);

        Carbon::setTestNow();
    }
}
