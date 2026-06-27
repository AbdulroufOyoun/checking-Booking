<?php

namespace Tests\Feature\Reservation;

use App\Models\Client;
use App\Models\Reservation;
use App\Models\Reservation_source;
use App\Models\ReservationDailyCharge;
use App\Models\ReservationPay;
use App\Models\Room;
use App\Models\Stay_reason;
use App\Services\CollectionsService;
use App\Services\ReservationFinancialService;
use Carbon\Carbon;
use Database\Seeders\ReservationTestDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentBalanceStabilityTest extends TestCase
{
    public function test_check_in_patch_does_not_increase_balance_after_full_payment(): void
    {
        Carbon::setTestNow('2026-06-15');

        $this->artisan('db:seed', [
            '--class' => ReservationTestDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $user = $this->userWithApiPermissions();
        $reservation = $this->createFullyPaidReservation($user);
        $this->assertNotNull($reservation);
        $this->assertLessThanOrEqual(0.005, $reservation->balanceDue());

        $patch = $this->actingAs($user, 'api')->patchJson("/api/users/reservations/{$reservation->id}", [
            'logedin' => 1,
        ]);
        $patch->assertOk();

        $reservation->refresh()->load('payments');
        $this->assertLessThanOrEqual(
            0.005,
            $reservation->balanceDue(),
            'Check-in must not recalculate pricing and restore balance after full payment.'
        );

        Carbon::setTestNow();
    }

    public function test_patch_with_unchanged_discount_does_not_restore_balance_after_full_payment(): void
    {
        Carbon::setTestNow('2026-06-15');

        $this->artisan('db:seed', [
            '--class' => ReservationTestDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $user = $this->userWithApiPermissions();
        $reservation = $this->createFullyPaidReservation($user);
        $this->assertNotNull($reservation);

        $patch = $this->actingAs($user, 'api')->patchJson("/api/users/reservations/{$reservation->id}", [
            'discount' => (float) $reservation->discount,
            'extras' => (float) $reservation->extras,
            'penalties' => (float) $reservation->penalties,
        ]);
        $patch->assertOk();

        $reservation->refresh()->load('payments');
        $this->assertLessThanOrEqual(0.005, $reservation->balanceDue());

        Carbon::setTestNow();
    }

    public function test_collect_all_clears_outstanding_balances_and_is_idempotent(): void
    {
        Carbon::setTestNow('2026-06-20');

        $this->artisan('db:seed', [
            '--class' => ReservationTestDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $user = $this->userWithApiPermissions(['view payments', 'view reservations', 'update reservations']);
        $before = app(CollectionsService::class)->summarize(Carbon::today());
        $this->assertGreaterThan(0, (int) $before['count']);

        $first = $this->actingAs($user, 'api')->postJson('/api/users/collections/collect-all');
        $first->assertOk();
        $first->assertJsonPath('success', true);
        $this->assertGreaterThan(0, (int) $first->json('data.collected_count'));

        $after = app(CollectionsService::class)->summarize(Carbon::today());
        $this->assertSame(0, (int) $after['count']);
        $this->assertEqualsWithDelta(0.0, (float) $after['total_balance'], 0.01);

        $second = $this->actingAs($user, 'api')->postJson('/api/users/collections/collect-all');
        $second->assertOk();
        $this->assertSame(0, (int) $second->json('data.collected_count'));
        $this->assertSame(0, (int) $second->json('data.skipped_count'));

        Carbon::setTestNow();
    }

    public function test_backfill_sync_base_does_not_restore_balance_after_collect_all(): void
    {
        Carbon::setTestNow('2026-06-20');

        $this->artisan('db:seed', [
            '--class' => ReservationTestDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $user = $this->userWithApiPermissions(['view payments', 'update reservations']);
        $collect = $this->actingAs($user, 'api')->postJson('/api/users/collections/collect-all');
        $collect->assertOk();

        $this->artisan('reservations:backfill-daily-charges', ['--sync-base' => true]);

        $summary = app(CollectionsService::class)->summarize(Carbon::today());
        $this->assertSame(0, (int) $summary['count'], 'Backfill must not recreate balances after full collection.');

        $withBalance = Reservation::query()
            ->excludingCancelled()
            ->whereIn('reservation_status', Reservation::cashReportStatuses())
            ->with('payments')
            ->get()
            ->filter(fn (Reservation $r) => $r->balanceDue() > 0.005);

        $this->assertCount(0, $withBalance);

        Carbon::setTestNow();
    }

    public function test_collect_all_via_service_matches_sum_of_balances(): void
    {
        Carbon::setTestNow('2026-06-20');

        $this->artisan('db:seed', [
            '--class' => ReservationTestDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $user = $this->apiTestUser();
        $expectedTotal = (float) app(CollectionsService::class)->summarize(Carbon::today())['total_balance'];

        $result = app(ReservationFinancialService::class)->collectAllOutstanding($user->id);

        $this->assertEqualsWithDelta($expectedTotal, (float) $result['collected_total'], 0.05);
        $this->assertSame((int) $result['collected_count'], count($result['items']));

        Carbon::setTestNow();
    }

    public function test_sync_totals_does_not_raise_total_after_full_payment_when_charges_drift(): void
    {
        Carbon::setTestNow('2026-06-15');

        $this->artisan('db:seed', [
            '--class' => ReservationTestDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $user = $this->userWithApiPermissions();
        $reservation = $this->createFullyPaidReservation($user);
        $this->assertNotNull($reservation);

        ReservationDailyCharge::query()
            ->where('reservation_id', $reservation->id)
            ->update(['base_amount' => DB::raw('base_amount + 50')]);

        app(ReservationFinancialService::class)->syncTotalsFromDailyCharges(
            $reservation->fresh(['payments', 'reservationRooms'])
        );
        $reservation->refresh()->load('payments');

        $this->assertLessThanOrEqual(0.005, $reservation->balanceDue());

        Carbon::setTestNow();
    }

    private function createFullyPaidReservation(\App\Models\User $user): ?Reservation
    {
        $client = Client::first();
        $stayReason = Stay_reason::first();
        $source = Reservation_source::first();
        $start = '2026-06-15';
        $end = '2026-06-18';
        $room = $this->findOrCreateAvailableRoom($start, $end);

        if (!$client || !$stayReason || !$source || !$room) {
            return null;
        }

        $create = $this->actingAs($user, 'api')->postJson('/api/users/makeReservation', [
            'client_id' => $client->id,
            'rooms' => [['room_id' => $room->id]],
            'start_date' => $start,
            'expire_date' => $end,
            'reservation_type' => 0,
            'reservation_status' => 1,
            'stay_reason_id' => $stayReason->id,
            'reservation_source_id' => $source->id,
            'rent_type' => 0,
            'price_calculation_mode' => 0,
            'discount' => 0,
            'extras' => 0,
            'penalties' => 0,
            'pay_amount' => 0,
            'logedin' => 0,
        ]);

        if ($create->status() !== 200) {
            return null;
        }

        $reservation = Reservation::with('payments')->find($create->json('data.id'));
        if (!$reservation) {
            return null;
        }

        $balance = $reservation->balanceDue();
        if ($balance <= 0.005) {
            return $reservation;
        }

        $pay = $this->actingAs($user, 'api')->postJson(
            "/api/users/reservations/{$reservation->id}/payments",
            ['pay' => round($balance, 2), 'type' => ReservationPay::TYPE_PAYMENT]
        );
        $pay->assertOk();

        return $reservation->fresh('payments');
    }
}
