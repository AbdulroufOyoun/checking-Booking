<?php

namespace Tests\Feature\Reservation;

use App\Models\Client;
use App\Models\RefundPolicy;
use App\Models\Reservation;
use App\Models\ReservationDailyCharge;
use App\Models\ReservationPay;
use App\Models\Reservation_source;
use App\Models\Room;
use App\Models\Stay_reason;
use Carbon\Carbon;
use Database\Seeders\RefundPolicySeeder;
use Database\Seeders\ReservationTestDataSeeder;
use Tests\TestCase;

class ShortenReservationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', [
            '--class' => RefundPolicySeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }

    public function test_shorten_updates_expire_date_and_removes_future_charges(): void
    {
        Carbon::setTestNow('2026-06-01');

        $user = $this->userWithApiPermissions(['update reservations', 'manage refunds']);
        $reservation = $this->createPaidReservation('2026-06-15', '2026-06-23', $user);
        $this->assertNotNull($reservation);

        $originalCharges = ReservationDailyCharge::where('reservation_id', $reservation->id)->count();
        $this->assertEquals(8, $originalCharges);

        $newExpire = '2026-06-19';
        $shorten = $this->actingAs($user, 'api')->patchJson(
            "/api/users/reservations/{$reservation->id}/shorten",
            ['expire_date' => $newExpire]
        );
        $shorten->assertStatus(200);
        $shorten->assertJsonPath('success', true);

        $reservation->refresh();
        $this->assertEquals($newExpire, $reservation->expire_date);
        $this->assertEquals(4, (int) $reservation->nights);

        $remainingCharges = ReservationDailyCharge::where('reservation_id', $reservation->id)->count();
        $this->assertEquals(4, $remainingCharges);
        $this->assertFalse(
            ReservationDailyCharge::where('reservation_id', $reservation->id)
                ->where('charge_date', '>=', $newExpire)
                ->exists()
        );

        Carbon::setTestNow();
    }

    public function test_partial_refund_preview_matches_refund_with_new_expire_date(): void
    {
        Carbon::setTestNow('2026-06-01');

        RefundPolicy::query()->where('timing', 'before_start')->delete();
        RefundPolicy::create([
            'name' => 'Partial cancel preview policy',
            'rent_type' => 0,
            'timing' => 'before_start',
            'threshold_mode' => RefundPolicy::THRESHOLD_FIXED_DAYS,
            'days_threshold' => 7,
            'days_before_checkin' => 7,
            'refund_percent' => 50,
            'refund_basis' => 'remaining_nights',
            'payment_status' => 2,
            'during_stay' => 0,
            'payment_statuses' => ['paid'],
        ]);

        $user = $this->userWithApiPermissions(['manage refunds']);
        $reservation = $this->createPaidReservation('2026-06-15', '2026-06-23', $user);
        $this->assertNotNull($reservation);

        $newExpire = '2026-06-19';
        $preview = $this->actingAs($user, 'api')->getJson(
            '/api/users/refund-policies/preview?reservation_id=' . $reservation->id
            . '&new_expire_date=' . $newExpire
        );
        $preview->assertStatus(200);
        $preview->assertJsonPath('success', true);
        $preview->assertJsonPath('data.breakdown.partial_cancel', true);
        $this->assertEquals(4, (int) $preview->json('data.breakdown.cancelled_nights_count'));

        $amount = (float) $preview->json('data.refund_amount');
        $this->assertGreaterThan(0, $amount);

        $refund = $this->actingAs($user, 'api')->postJson('/api/users/refund', [
            'reservation_id' => $reservation->id,
            'new_expire_date' => $newExpire,
        ]);
        $refund->assertStatus(200);
        $refund->assertJsonPath('success', true);
        $this->assertEqualsWithDelta($amount, (float) $refund->json('data.amount'), 0.01);
        $refund->assertJsonPath('data.partial_cancel', true);

        $reservation->refresh();
        $this->assertEquals($newExpire, $reservation->expire_date);
        $this->assertNotEquals(Reservation::STATUS_CANCELLED, (int) $reservation->reservation_status);

        Carbon::setTestNow();
    }

    public function test_shorten_rejects_invalid_new_expire_date(): void
    {
        Carbon::setTestNow('2026-06-01');

        $user = $this->userWithApiPermissions(['update reservations']);
        $reservation = $this->createPaidReservation('2026-06-15', '2026-06-23', $user);
        $this->assertNotNull($reservation);

        $sameAsExpire = $this->actingAs($user, 'api')->patchJson(
            "/api/users/reservations/{$reservation->id}/shorten",
            ['expire_date' => '2026-06-23']
        );
        $sameAsExpire->assertStatus(422);

        $beforeStart = $this->actingAs($user, 'api')->patchJson(
            "/api/users/reservations/{$reservation->id}/shorten",
            ['expire_date' => '2026-06-15']
        );
        $beforeStart->assertStatus(422);

        Carbon::setTestNow();
    }

    private function createPaidReservation(string $startDate, string $endDate, \App\Models\User $user): ?Reservation
    {
        $this->artisan('db:seed', [
            '--class' => ReservationTestDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $client = Client::first();
        $stayReason = Stay_reason::first();
        $source = Reservation_source::first();
        if (!$client || !$stayReason || !$source) {
            return null;
        }

        $room = $this->findOrCreateAvailableRoom($startDate, $endDate)
            ?? $this->createIsolatedTestRoom();
        if (!$room) {
            return null;
        }

        $create = $this->actingAs($user, 'api')->postJson('/api/users/makeReservation', [
            'client_id' => $client->id,
            'rooms' => [['room_id' => $room->id]],
            'start_date' => $startDate,
            'expire_date' => $endDate,
            'reservation_type' => 0,
            'reservation_status' => 1,
            'stay_reason_id' => $stayReason->id,
            'reservation_source_id' => $source->id,
            'rent_type' => 0,
            'price_calculation_mode' => 0,
            'discount' => 0,
            'extras' => 0,
            'penalties' => 0,
        ]);

        if ($create->status() !== 200 || !$create->json('success')) {
            $this->fail('makeReservation failed: ' . ($create->json('message') ?? $create->getContent()));
        }

        $id = $create->json('data.id') ?? $create->json('data.reservation.id') ?? null;
        $reservation = $id ? Reservation::find($id) : Reservation::latest('id')->first();
        if (!$reservation) {
            return null;
        }

        $pay = $this->actingAs($user, 'api')->postJson(
            "/api/users/reservations/{$reservation->id}/payments",
            ['pay' => (float) $reservation->total, 'type' => ReservationPay::TYPE_PAYMENT]
        );

        if ($pay->status() !== 200 || !$pay->json('success')) {
            return null;
        }

        return $reservation->fresh(['payments']);
    }
}
