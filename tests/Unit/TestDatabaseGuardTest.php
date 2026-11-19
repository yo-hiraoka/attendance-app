<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\TestDatabaseGuard;

class TestDatabaseGuardTest extends TestCase
{
    private function safeConnection(): array
    {
        return [
            'driver' => 'mysql', 'host' => 'mysql', 'port' => '3306',
            'database' => 'testing', 'username' => 'attendance_test',
            'unix_socket' => '', 'options' => [],
        ];
    }

    public function test_safe_connection_is_allowed_without_connecting(): void
    {
        TestDatabaseGuard::check($this->safeConnection(), false, false, false, false);
        $this->assertTrue(true);
    }

    public function test_unsafe_connection_settings_are_rejected_without_exposing_secrets(): void
    {
        $secret = 'secret-test-value';
        $cases = [
            array_merge($this->safeConnection(), ['database' => 'laravel']),
            array_merge($this->safeConnection(), ['database' => 'other']),
            array_merge($this->safeConnection(), ['username' => 'sail']),
            array_merge($this->safeConnection(), ['username' => 'root']),
            array_merge($this->safeConnection(), ['username' => 'other']),
            array_merge($this->safeConnection(), ['host' => 'elsewhere']),
            array_merge($this->safeConnection(), ['driver' => 'sqlite']),
            array_merge($this->safeConnection(), ['port' => '3307']),
            array_merge($this->safeConnection(), ['unix_socket' => '/tmp/mysql.sock']),
            array_merge($this->safeConnection(), ['read' => ['host' => 'elsewhere']]),
            array_merge($this->safeConnection(), ['write' => ['database' => 'laravel']]),
            array_merge($this->safeConnection(), ['url' => $secret]),
            array_merge($this->safeConnection(), ['options' => ['secret' => $secret]]),
        ];
        foreach ($cases as $connection) {
            try {
                TestDatabaseGuard::check($connection, false, false, false, false);
                $this->fail('Unsafe connection was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertStringNotContainsString($secret, $exception->getMessage());
            }
        }
    }

    public function test_testing_file_is_required_and_inherited_password_is_rejected(): void
    {
        TestDatabaseGuard::checkEnvironment(true, false);
        $this->assertTrue(true);

        foreach ([[false, false], [true, true]] as [$fileExists, $passwordSet]) {
            try {
                TestDatabaseGuard::checkEnvironment($fileExists, $passwordSet);
                $this->fail('Unsafe environment was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_urls_cache_and_parallel_are_rejected(): void
    {
        foreach ([
            [false, 'secret-url', false, false],
            [false, false, 'secret-url', false],
            [true, false, false, false],
            [false, false, false, true],
        ] as [$cached, $databaseUrl, $dbUrl, $parallel]) {
            try {
                TestDatabaseGuard::check($this->safeConnection(), $cached, $databaseUrl, $dbUrl, $parallel);
                $this->fail('Unsafe execution was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertStringNotContainsString('secret-url', $exception->getMessage());
            }
        }
    }

    public function test_guard_stops_bootstrap_before_subsequent_bootstrappers(): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $app['config']->set('database.default', 'mysql');
            $app['config']->set('database.connections.mysql', array_merge($this->safeConnection(), ['database' => 'laravel']));
        });
        TestDatabaseGuard::register($app);
        GuardSentinelBootstrapper::$ran = false;

        try {
            $app->bootstrapWith([LoadConfiguration::class, GuardSentinelBootstrapper::class]);
            $this->fail('Unsafe configuration was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertFalse(GuardSentinelBootstrapper::$ran);
        }
    }

    public function test_missing_testing_file_stops_before_environment_bootstrap(): void
    {
        $app = new Application(sys_get_temp_dir().'/missing-test-environment-'.uniqid());
        TestDatabaseGuard::register($app);
        GuardSentinelBootstrapper::$ran = false;

        try {
            $app->bootstrapWith([LoadEnvironmentVariables::class, GuardSentinelBootstrapper::class]);
            $this->fail('Missing testing environment was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertFalse(GuardSentinelBootstrapper::$ran);
        }
    }

    public function test_environment_url_and_parallel_flags_are_detected(): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository([
            'database' => [
                'default' => 'mysql',
                'connections' => ['mysql' => $this->safeConnection()],
            ],
        ]));

        foreach (['DB_URL', 'DATABASE_URL', 'LARAVEL_PARALLEL_TESTING', 'TEST_TOKEN'] as $key) {
            $originalEnv = $_ENV[$key] ?? null;
            $originalServer = $_SERVER[$key] ?? null;
            $originalProcess = getenv($key);
            try {
                $_ENV[$key] = 'sensitive-test-value';
                try {
                    TestDatabaseGuard::assertSafe($app);
                    $this->fail('Unsafe environment was accepted.');
                } catch (RuntimeException $exception) {
                    $this->assertStringNotContainsString('sensitive-test-value', $exception->getMessage());
                }
            } finally {
                if ($originalEnv === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $originalEnv;
                }
                if ($originalServer === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $originalServer;
                }
                $originalProcess === false ? putenv($key) : putenv($key.'='.$originalProcess);
            }
        }
    }
}

class GuardSentinelBootstrapper
{
    public static bool $ran = false;

    public function bootstrap(Application $app): void
    {
        self::$ran = true;
    }
}
