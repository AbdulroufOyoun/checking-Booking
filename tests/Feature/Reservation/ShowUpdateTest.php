<?php

namespace Tests\Feature\Reservation;

use App\Models\Client;
use App\Models\Reservation;
use App\Models\Reservation_source;
use App\Models\Room;
use App\Models\Stay_reason;
use App\Services\ReservationRoomStatusService;
use Carbon\Carbon;
use Database\Seeders\ReservationTestDataSeeder;
use Tests\TestCase;

class ShowUpdateTest extends TestCase
{
    public function test_show_and_patch_reservation(): void
    {
        Carbon::setTestNow('2026-06-15');

        $this->artisan('db:seed', [
            '--class' => ReservationTestDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $user = $this->userWithApiPermissions();
        $client = Client::first();
        $stayReason = Stay_reason::first();
        $source = Reservation_source::first();
        $room = Room::where('active', 1)->whereHas('roomType')->first();

        $this->assertNotNull($client);
        $this->assertNotNull($stayReason);
        $this->assertNotNull($source);
        $this->assertNotNull($room);

        Room::where('id', $room->id)->update([
            'roomStatus' => ReservationRoomStatusService::ROOM_AVAILABLE,
        ]);

        $create = $this->actingAs($user, 'api')->postJson('/api/users/makeReservation', [
            'client_id' => $client->id,
            'rooms' => [['room_id' => $room->id]],
            'start_date' => '2026-06-15',
            'expire_date' => '2026-06-18',
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

        $create->assertStatus(200);
        $create->assertJsonPath('success', true);

        $reservationId = $create->json('data.id');
        $this->assertNotNull($reservationId);

        $show = $this->actingAs($user, 'api')->getJson("/api/users/reservations/{$reservationId}");
        $show->assertStatus(200);
        $show->assertJsonPath('success', true);
        $show->assertJsonPath('data.reservation.id', $reservationId);

        $patch = $this->actingAs($user, 'api')->patchJson("/api/users/reservations/{$reservationId}", [
            'logedin' => 1,
        ]);
        $patch->assertStatus(200);
        $patch->assertJsonPath('data.reservation.logedin', 1);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'logedin' => 1,
        ]);

        Carbon::setTestNow();
    }
}
