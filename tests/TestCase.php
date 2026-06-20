<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\InteractsWithApi;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use InteractsWithApi;

    private static ?\App\Models\User $cachedPermissionUser = null;

    private static string $cachedPermissionKey = '';

    /** Dedicated API test user — never mutate production admin (001). */
    protected function apiTestUser(): \App\Models\User
    {
        $user = \App\Models\User::firstOrCreate(
            ['job_number' => 'TEST-API'],
            [
                'name' => 'API Test User',
                'email' => 'test-api@hotel.test',
                'mobile' => '0599999998',
                'active' => 1,
                'password' => bcrypt('test-api-password'),
            ]
        );

        return $user;
    }

    protected function userWithApiPermissions(array $extra = []): \App\Models\User
    {
        $user = $this->apiTestUser();

        $names = array_values(array_unique(array_merge([
            'view reservations', 'create reservations', 'update reservations', 'cancel reservations',
            'view earnings', 'view revenue', 'view payments', 'manage refunds',
            'view buildings', 'view rooms', 'view clients',
        ], $extra)));
        sort($names);
        $cacheKey = implode('|', $names);

        if (self::$cachedPermissionUser !== null && self::$cachedPermissionKey === $cacheKey) {
            return self::$cachedPermissionUser;
        }

        foreach ($names as $name) {
            \Spatie\Permission\Models\Permission::findOrCreate($name, 'api');
        }

        $user->syncPermissions($names);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        self::$cachedPermissionUser = $user;
        self::$cachedPermissionKey = $cacheKey;

        return $user;
    }
}
