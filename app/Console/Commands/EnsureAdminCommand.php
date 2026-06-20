<?php

namespace App\Console\Commands;

use Database\Seeders\AdminSeeder;
use Illuminate\Console\Command;

class EnsureAdminCommand extends Command
{
    protected $signature = 'hotel:ensure-admin';

    protected $description = 'Restore job number 001 admin role and sync all API permissions';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => AdminSeeder::class, '--force' => true]);

        $this->info('Admin account 001 is restored with the admin role and full permissions.');
        $this->line('Log out and log in again in the Angular app to refresh your session.');

        return self::SUCCESS;
    }
}
