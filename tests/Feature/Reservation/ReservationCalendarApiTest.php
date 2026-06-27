<?php

namespace Tests\Feature\Reservation;

use App\Models\Reservation;
use Carbon\Carbon;
use Database\Seeders\ReservationTestDataSeeder;
use Tests\TestCase;

class ReservationCalendarApiTest extends TestCase
{
    public function test_calendar_event_end_matches_expire_date_not_next_day(): void
    {
        Carbon::setTestNow('2026-06-20');

        $this->artisan('db:seed', [
            '--class' => ReservationTestDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $user = $this->userWithApiPermissions();

        $reservation = Reservation::query()
            ->where('start_date', '2026-06-12')
            ->where('expire_date', '2026-06-20')
            ->first();

        $this->assertNotNull($reservation, 'Expected seeded reservation 12→20 Jun');

        $calendar = $this->actingAs($user, 'api')->getJson(
            '/api/users/reservations/calendar?date_from=2026-06-01&date_to=2026-06-30'
        );

        $calendar->assertOk();
        $event = collect($calendar->json('data.events'))->firstWhere('id', $reservation->id);
        $this->assertNotNull($event);
        $this->assertSame('2026-06-12', $event['start']);
        $this->assertSame('2026-06-20', $event['end'], 'Calendar API end must equal expire_date (checkout day)');

        $list = $this->actingAs($user, 'api')->getJson(
            '/api/users/reservations?date_from=2026-06-01&date_to=2026-06-30&perPage=50'
        );
        $list->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $reservation->id);
        $this->assertNotNull($row);
        $this->assertSame('2026-06-20', $row['expire_date']);
        $this->assertSame($row['expire_date'], $event['end'], 'List checkout and calendar end must match');

        Carbon::setTestNow();
    }
}
