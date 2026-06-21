<?php

namespace Tests\Feature\Reservation;

use App\Models\Client;
use App\Models\Reservation;
use App\Models\ReservationPay;
use App\Models\Reservation_source;
use App\Models\Room;
use App\Models\Stay_reason;
use App\Services\ReservationRoomStatusService;
use Carbon\Carbon;
use Database\Seeders\RefundPolicySeeder;
use Database\Seeders\ReservationTestDataSeeder;
use Tests\TestCase;

class RefundPolicyTest extends TestCase
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

    public function test_preview_rejects_completed_stay(): void
    {
        Carbon::setTestNow('2026-09-01');

        $user = $this->userWithApiPermissions(['manage refunds']);
        $reservation = $this->createPaidReservation('2026-09-10', '2026-09-15', $user);
        $this->assertNotNull($reservation);

        $reservation->update(['expire_date' => '2026-08-31', 'start_date' => '2026-08-20']);

        $response = $this->actingAs($user, 'api')->getJson(
            '/api/users/refund-policies/preview?reservation_id=' . $reservation->id
        );

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);

        Carbon::setTestNow();
    }

    public function test_preview_and_refund_amount_match(): void
    {
        Carbon::setTestNow('2026-06-01');

        $user = $this->userWithApiPermissions(['manage refunds']);
        $reservation = $this->createPaidReservation('2026-06-15', '2026-06-20', $user);
        $this->assertNotNull($reservation);

        $preview = $this->actingAs($user, 'api')->getJson(
            '/api/users/refund-policies/preview?reservation_id=' . $reservation->id
        );
        $preview->assertStatus(200);
        $preview->assertJsonPath('success', true);

        $amount = (float) $preview->json('data.refund_amount');
        $this->assertGreaterThan(0, $amount);

        $refund = $this->actingAs($user, 'api')->postJson('/api/users/refund', [
            'reservation_id' => $reservation->id,
        ]);
        $refund->assertStatus(200);
        $refund->assertJsonPath('success', true);
        $this->assertEqualsWithDelta($amount, (float) $refund->json('data.amount'), 0.01);

        $reservation->refresh();
        $this->assertEquals(Reservation::STATUS_CANCELLED, (int) $reservation->reservation_status);

        Carbon::setTestNow();
    }

    public function test_refund_appears_in_earnings_after_cancelled(): void
    {
        Carbon::setTestNow('2026-06-01');

        $user = $this->userWithApiPermissions(['manage refunds']);
        $reservation = $this->createPaidReservation('2026-06-15', '2026-06-20', $user);
        $this->assertNotNull($reservation);

        $refund = $this->actingAs($user, 'api')->postJson('/api/users/refund', [
            'reservation_id' => $reservation->id,
        ]);
        $refund->assertStatus(200);
        $amount = (float) $refund->json('data.amount');

        $summary = $this->actingAs($user, 'api')->getJson(
            '/api/users/earnings-summary?start_date=2026-06-01&end_date=2026-06-30'
        );
        $summary->assertStatus(200);
        $this->assertGreaterThanOrEqual($amount, (float) $summary->json('data.total_out'));

        Carbon::setTestNow();
    }

    public function test_refund_policy_crud(): void
    {
        $user = $this->userWithApiPermissions(['manage refund policies']);

        $create = $this->actingAs($user, 'api')->postJson('/api/users/refund-policies', [
            'name' => 'Test policy',
            'rent_type' => 0,
            'timing' => 'before_start',
            'days_threshold' => 10,
            'refund_percent' => 75,
            'refund_basis' => 'total',
            'payment_status' => 2,
        ]);
        $create->assertStatus(200);
        $id = $create->json('data.id');
        $this->assertNotNull($id);

        $this->actingAs($user, 'api')->getJson('/api/users/refund-policies')
            ->assertStatus(200);

        $this->actingAs($user, 'api')->putJson("/api/users/refund-policies/{$id}", [
            'refund_percent' => 60,
        ])->assertStatus(200);

        $this->actingAs($user, 'api')->deleteJson('/api/users/refund-policies', ['id' => $id])
            ->assertStatus(200);
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

        $room = null;
        foreach (Room::where('active', 1)->where('roomStatus', ReservationRoomStatusService::ROOM_AVAILABLE)->whereHas('roomType')->get() as $candidate) {
            $overlap = \App\Models\ReservationRoom::where('room_id', $candidate->id)
                ->whereHas('reservation', function ($query) use ($startDate, $endDate) {
                    $query->where('start_date', '<', $endDate)
                        ->where('expire_date', '>', $startDate);
                })->exists();
            if (!$overlap) {
                $room = $candidate;
                break;
            }
        }

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
            return null;
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
