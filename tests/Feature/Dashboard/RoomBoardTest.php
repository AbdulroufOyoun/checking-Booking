<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Database\Seeders\ReservationTestDataSeeder;
use Tests\TestCase;

class RoomBoardTest extends TestCase
{
    public function test_occupancy_board_for_demo_date(): void
    {
        $this->artisan('db:seed', [
            '--class' => ReservationTestDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $user = $this->userWithApiPermissions();

        $response = $this->actingAs($user, 'api')->getJson('/api/users/rooms/occupancy-board?date=2026-08-01');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $summary = $response->json('data.summary') ?? $response->json('data.data.summary');
        $this->assertNotNull($summary);
        $this->assertGreaterThan(0, $summary['total'] ?? 0);

        $occupied = ($summary['in_house'] ?? 0)
            + ($summary['reserved'] ?? 0)
            + ($summary['check_in_today'] ?? 0)
            + ($summary['check_out_today'] ?? 0);

        $this->assertGreaterThan(0, $occupied);
    }
}
