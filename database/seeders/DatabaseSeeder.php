<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seeders = [
            PermissionSeeder::class,
            ChartOfAccountsSeeder::class,
            PeakDayAndMonthSeeder::class,
            RefundPolicySeeder::class,
            AdminSeeder::class,
        ];

        if (!filter_var(env('DEMO_LIGHT', false), FILTER_VALIDATE_BOOL)) {
            $seeders[] = HotelDemoSeeder::class;
        } else {
            $this->command?->info('DEMO_LIGHT enabled — skipping HotelDemoSeeder (~650 reservations).');
        }

        $seeders[] = DemoBootstrapSeeder::class;

        $this->call($seeders);
    }
}
