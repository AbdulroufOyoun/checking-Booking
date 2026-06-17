<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    private static ?\App\Models\User $cachedPermissionUser = null;

    private static string $cachedPermissionKey = '';

    protected function userWithApiPermissions(array $extra = []): \App\Models\User
    {
        $user = \App\Models\User::first();
        $this->assertNotNull($user);

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
