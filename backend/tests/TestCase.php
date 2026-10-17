<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Enforce safety guard against non-test MySQL databases.
     *
     * @param \Illuminate\Contracts\Config\Repository|\Illuminate\Config\Repository|null $config
     * @throws \RuntimeException
     */
    public static function assertSafeTestDatabase($config = null): void
    {
        $get = function (string $key) use ($config) {
            if (is_array($config)) {
                return data_get($config, $key);
            }
            if (is_object($config) && method_exists($config, 'get')) {
                return $config->get($key);
            }
            return config($key);
        };

        $defaultConn = $get('database.default');
        $driver = $get("database.connections.{$defaultConn}.driver") ?? $defaultConn;

        if ($defaultConn === 'mysql' || $driver === 'mysql') {
            $dbName = $get("database.connections.{$defaultConn}.database");
            $dbName = is_string($dbName) ? trim($dbName) : '';

            if ($dbName === '' || !str_ends_with($dbName, '_test')) {
                throw new \RuntimeException(
                    "SAFETY GUARD ABORT: MySQL tests cannot target production or non-test database '{$dbName}'. Target database must end in '_test'."
                );
            }
        }
    }

    public function createApplication()
    {
        $app = parent::createApplication();

        self::assertSafeTestDatabase($app['config']);

        return $app;
    }

    protected function beforeRefreshingDatabase()
    {
        self::assertSafeTestDatabase(config());
    }

    protected function setUp(): void
    {
        // Enforce safety guard before parent::setUp() executes traits or migrations
        if ($this->app) {
            self::assertSafeTestDatabase(config());
        }

        parent::setUp();

        $this->app->instance(\App\Ems\Services\Ticketing\QrCodeGenerator::class, new Support\FakeQrCodeGenerator());
    }

    protected function volunteerRole(): Role
    {
        return Role::firstOrCreate(
            ['slug' => 'volunteer'],
            ['name' => 'Volunteer', 'uuid' => (string) Str::uuid()]
        );
    }

    protected function createVolunteerUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->sync([$this->volunteerRole()->id]);

        return $user;
    }

    protected function assignVolunteerRole(User $user): User
    {
        $user->roles()->syncWithoutDetaching([$this->volunteerRole()->id]);

        return $user;
    }
}
