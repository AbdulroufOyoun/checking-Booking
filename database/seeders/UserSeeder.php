<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user with fixed ID 1
        $admin = User::updateOrCreate(
            ['id' => 1],
            [
                'name'          => 'Admin',
                'email'         => 'admin@hotel.com',
                'job_number'    => '001',
                'jobtitle_id'   => null,
                'department_id' => null,
                'mobile'        => '0555555555',
                'discount_id'   => null,
                'active'        => 1,
                'password'      => Hash::make('admin123'),
            ]
        );

        // Assign admin role to admin user (Role created in PermissionSeeder)
        $admin->assignRole('admin');

        $this->command->info('Admin user with ID 1 created successfully and assigned to admin role!');
    }
}
