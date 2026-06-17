<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DemoSetupCommand extends Command
{
    protected $signature = 'demo:setup
                            {--fresh : Run migrate:fresh before seeding}
                            {--light : Skip HotelDemoSeeder (ReservationTestDataSeeder only)}';

    protected $description = 'Bootstrap demo database (seed + backfills)';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->info('Running migrate:fresh...');
            Artisan::call('migrate:fresh', ['--force' => true]);
            $this->line(Artisan::output());
        }

        if ($this->option('light')) {
            putenv('DEMO_LIGHT=1');
            $_ENV['DEMO_LIGHT'] = '1';
        }

        $this->info('Seeding database...');
        Artisan::call('db:seed', ['--force' => true]);
        $this->line(Artisan::output());

        return $this->call('demo:verify');
    }
}
