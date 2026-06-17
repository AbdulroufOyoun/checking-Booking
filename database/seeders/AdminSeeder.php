<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Client;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Default admin credentials (change after first login in production).
     */
    public const JOB_NUMBER = '001';
    public const PASSWORD = 'admin123';
    public const EMAIL = 'admin@hotel.com';

    public function run(): void
    {
        if (!Schema::hasTable('roles')
            || !Role::where('name', 'admin')->where('guard_name', 'api')->exists()) {
            $this->call(PermissionSeeder::class);
        }

        $this->ensurePassportPersonalClient();

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        if (!$adminRole) {
            $this->command->error('Admin role not found. PermissionSeeder may have failed.');
            return;
        }

        // Keep admin role synced with every permission (including newly added ones).
        $adminRole->syncPermissions(Permission::where('guard_name', 'api')->get());

        $admin = User::updateOrCreate(
            ['job_number' => self::JOB_NUMBER],
            [
                'name' => 'Admin',
                'email' => self::EMAIL,
                'jobtitle_id' => null,
                'department_id' => null,
                'mobile' => '0555555555',
                'discount_id' => null,
                'active' => 1,
                'password' => Hash::make(self::PASSWORD),
            ]
        );

        $admin->syncRoles([$adminRole->name]);
        // Role is the single source of truth; drop legacy direct permission rows.
        $admin->syncPermissions([]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Admin user seeded successfully.');
        $this->command->line('  Job number : ' . self::JOB_NUMBER);
        $this->command->line('  Password   : ' . self::PASSWORD);
        $this->command->line('  Email      : ' . self::EMAIL);
    }

    private function ensurePassportPersonalClient(): void
    {
        if (!Schema::hasTable('oauth_clients')) {
            $this->command->warn('Passport oauth_clients table missing. Run: php artisan migrate');
            return;
        }

        if (Client::query()->where('personal_access_client', true)->exists()) {
            return;
        }

        Artisan::call('passport:client', [
            '--personal' => true,
            '--name' => 'Hotel System Personal Access',
            '--no-interaction' => true,
        ]);

        $this->command->info('Passport personal access client created.');
    }
}
