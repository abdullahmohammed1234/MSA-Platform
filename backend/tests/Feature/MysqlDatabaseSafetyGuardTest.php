<?php

namespace Tests\Feature;

use Illuminate\Config\Repository as ConfigRepository;
use Tests\TestCase;

class MysqlDatabaseSafetyGuardTest extends TestCase
{
    /**
     * Requirement 1: sfumsa_platform_test is accepted.
     */
    public function test_sfumsa_platform_test_is_accepted(): void
    {
        $config = [
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'database' => 'sfumsa_platform_test',
                    ],
                ],
            ],
        ];

        // Should not throw an exception
        TestCase::assertSafeTestDatabase($config);
        $this->assertTrue(true);
    }

    /**
     * Requirement 2: Another valid test database name ending in _test is accepted.
     */
    public function test_another_valid_test_database_name_ending_in_test_is_accepted(): void
    {
        $config = [
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'database' => 'secondary_app_test',
                    ],
                ],
            ],
        ];

        TestCase::assertSafeTestDatabase($config);
        $this->assertTrue(true);
    }

    /**
     * Requirement 3: The development database 'msa' is rejected.
     */
    public function test_development_database_msa_is_rejected(): void
    {
        $config = [
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'database' => 'msa',
                    ],
                ],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("SAFETY GUARD ABORT: MySQL tests cannot target production or non-test database 'msa'. Target database must end in '_test'.");

        TestCase::assertSafeTestDatabase($config);
    }

    /**
     * Requirement 4: A database name not ending in _test is rejected.
     */
    public function test_database_name_not_ending_in_test_is_rejected(): void
    {
        $config = [
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'database' => 'production_sfumsa',
                    ],
                ],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("SAFETY GUARD ABORT: MySQL tests cannot target production or non-test database 'production_sfumsa'. Target database must end in '_test'.");

        TestCase::assertSafeTestDatabase($config);
    }

    /**
     * Requirement 5: A database name ending in a different suffix, such as _testing, is rejected.
     */
    public function test_database_name_ending_in_different_suffix_such_as_testing_is_rejected(): void
    {
        $config = [
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'database' => 'sfumsa_platform_testing',
                    ],
                ],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("SAFETY GUARD ABORT: MySQL tests cannot target production or non-test database 'sfumsa_platform_testing'. Target database must end in '_test'.");

        TestCase::assertSafeTestDatabase($config);
    }

    /**
     * Requirement 6: An empty or missing database name is rejected when the MySQL test guard is active.
     */
    public function test_empty_or_missing_database_name_is_rejected(): void
    {
        // 6a: Empty string
        $emptyConfig = [
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'database' => '',
                    ],
                ],
            ],
        ];

        $caughtEmpty = false;
        try {
            TestCase::assertSafeTestDatabase($emptyConfig);
        } catch (\RuntimeException $e) {
            $caughtEmpty = true;
            $this->assertStringContainsString("SAFETY GUARD ABORT", $e->getMessage());
        }
        $this->assertTrue($caughtEmpty, "Expected RuntimeException for empty database name");

        // 6b: Whitespace string
        $whitespaceConfig = [
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'database' => '   ',
                    ],
                ],
            ],
        ];

        $caughtWhitespace = false;
        try {
            TestCase::assertSafeTestDatabase($whitespaceConfig);
        } catch (\RuntimeException $e) {
            $caughtWhitespace = true;
            $this->assertStringContainsString("SAFETY GUARD ABORT", $e->getMessage());
        }
        $this->assertTrue($caughtWhitespace, "Expected RuntimeException for whitespace database name");

        // 6c: Missing / null database name
        $nullConfig = [
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'database' => null,
                    ],
                ],
            ],
        ];

        $caughtNull = false;
        try {
            TestCase::assertSafeTestDatabase($nullConfig);
        } catch (\RuntimeException $e) {
            $caughtNull = true;
            $this->assertStringContainsString("SAFETY GUARD ABORT", $e->getMessage());
        }
        $this->assertTrue($caughtNull, "Expected RuntimeException for null database name");
    }

    /**
     * Requirement 7: Non-MySQL configurations (such as SQLite :memory:) are unaffected.
     */
    public function test_non_mysql_test_configurations_are_unaffected(): void
    {
        // SQLite memory configuration
        $sqliteConfig = [
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                    ],
                ],
            ],
        ];

        TestCase::assertSafeTestDatabase($sqliteConfig);
        $this->assertTrue(true);

        // PostgreSQL configuration without _test suffix
        $pgsqlConfig = [
            'database' => [
                'default' => 'pgsql',
                'connections' => [
                    'pgsql' => [
                        'driver' => 'pgsql',
                        'database' => 'standard_postgres_db',
                    ],
                ],
            ],
        ];

        TestCase::assertSafeTestDatabase($pgsqlConfig);
        $this->assertTrue(true);
    }

    /**
     * Requirement 8: The guard operates on config repository instances and effective configuration.
     */
    public function test_guard_operates_on_config_repository_instance(): void
    {
        $repo = new ConfigRepository([
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'database' => 'sfumsa_platform_test',
                    ],
                ],
            ],
        ]);

        TestCase::assertSafeTestDatabase($repo);
        $this->assertTrue(true);

        $disallowedRepo = new ConfigRepository([
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'database' => 'msa',
                    ],
                ],
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("SAFETY GUARD ABORT: MySQL tests cannot target production or non-test database 'msa'. Target database must end in '_test'.");
        TestCase::assertSafeTestDatabase($disallowedRepo);
    }

    /**
     * Requirement 8 (Lifecycle): The guard runs early during createApplication()
     * before any test harness traits, migrations, or cleanup can execute.
     */
    public function test_guard_prevents_unsafe_application_instantiation(): void
    {
        // Construct an isolated anonymous TestCase subclass and verify createApplication aborts
        $unsafeTest = new class('test_lifecycle') extends TestCase {
            public function runCreateAppWithUnsafeConfig(): void
            {
                // Temporarily override DB_DATABASE in putenv and verify createApplication throws
                $originalDb = getenv('DB_DATABASE');
                $originalConn = getenv('DB_CONNECTION');
                putenv('DB_CONNECTION=mysql');
                putenv('DB_DATABASE=msa');
                $_ENV['DB_CONNECTION'] = 'mysql';
                $_ENV['DB_DATABASE'] = 'msa';
                $_SERVER['DB_CONNECTION'] = 'mysql';
                $_SERVER['DB_DATABASE'] = 'msa';

                try {
                    $this->createApplication();
                    $failed = false;
                } catch (\RuntimeException $e) {
                    $failed = true;
                    if (!str_contains($e->getMessage(), "SAFETY GUARD ABORT")) {
                        throw $e;
                    }
                } finally {
                    // Restore environment safely
                    if ($originalConn !== false) {
                        putenv("DB_CONNECTION={$originalConn}");
                        $_ENV['DB_CONNECTION'] = $originalConn;
                        $_SERVER['DB_CONNECTION'] = $originalConn;
                    } else {
                        putenv('DB_CONNECTION');
                        unset($_ENV['DB_CONNECTION'], $_SERVER['DB_CONNECTION']);
                    }

                    if ($originalDb !== false) {
                        putenv("DB_DATABASE={$originalDb}");
                        $_ENV['DB_DATABASE'] = $originalDb;
                        $_SERVER['DB_DATABASE'] = $originalDb;
                    } else {
                        putenv('DB_DATABASE');
                        unset($_ENV['DB_DATABASE'], $_SERVER['DB_DATABASE']);
                    }
                }

                if (!$failed) {
                    throw new \Exception("createApplication() failed to abort when DB_DATABASE=msa on MySQL connection");
                }
            }
        };

        $unsafeTest->runCreateAppWithUnsafeConfig();
        $this->assertTrue(true);
    }

    /**
     * Verify that when MySQL configuration is loaded, the effective database
     * is sfumsa_platform_test and PDO driver is mysql.
     */
    public function test_effective_mysql_connection_verification(): void
    {
        $defaultConn = config('database.default');

        if ($defaultConn === 'mysql') {
            $effectiveDb = config('database.connections.mysql.database');
            $this->assertEquals('sfumsa_platform_test', $effectiveDb);

            $pdo = \Illuminate\Support\Facades\DB::connection('mysql')->getPdo();
            $this->assertEquals('mysql', $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
            $this->assertEquals('sfumsa_platform_test', \Illuminate\Support\Facades\DB::connection('mysql')->getDatabaseName());
        } else {
            // Under default phpunit.xml, DB_CONNECTION is sqlite :memory:
            $this->assertEquals('sqlite', $defaultConn);
        }
    }
}
