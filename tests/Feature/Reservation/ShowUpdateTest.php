<?php

namespace Tests\Feature\Reservation;

use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\ReservationTestDataSeeder;
use Tests\TestCase;

class ShowUpdateTest extends TestCase
{
    public function test_show_and_patch_reservation(): void
    {
        $this->artisan('db:seed', [
            '--class' => ReservationTestDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $user = $this->userWithApiPermissions();
        $reservation = Reservation::where('reservation_status', 1)->first();
        $this->assertNotNull($reservation);

        $show = $this->actingAs($user, 'api')->getJson("/api/users/reservations/{$reservation->id}");
        $show->assertStatus(200);
        $show->assertJsonPath('success', true);
        $show->assertJsonPath('data.reservation.id', $reservation->id);

        $patch = $this->actingAs($user, 'api')->patchJson("/api/users/reservations/{$reservation->id}", [
            'logedin' => 1,
        ]);
        $patch->assertStatus(200);
        $patch->assertJsonPath('data.reservation.logedin', 1);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'logedin' => 1,
        ]);
    }
}
