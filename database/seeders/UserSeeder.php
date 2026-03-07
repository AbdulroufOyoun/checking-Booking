<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\User_permission;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name'          => 'Admin',
            'job_number'    => '001',
            'jobtitle_id'   => null,
            'department_id' => null,
            'mobile'        => '0555555555',
            'email'         => 'admin@hotel.com',
            'discount_id'   => null,
            'active'        => 1,
            'password'      => Hash::make('admin123'),
        ]);

        // Get all permissions
        $permissions = Permission::all();

        // Give all permissions to admin user
        foreach ($permissions as $permission) {
            User_permission::create([
                'user_id'       => $admin->id,
                'permission_id' => $permission->id,
            ]);
        }

        $this->command->info('Admin user created successfully with all permissions!');
    }
}
