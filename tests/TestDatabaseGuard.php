<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use RuntimeException;

class TestDatabaseGuard
{
    private static ?string $loadedPasswordFingerprint = null;

    public static function register(Application $app): void
    {
        $app->beforeBootstrapping(LoadEnvironmentVariables::class, function (Application $app): void {
            self::checkEnvironment(
                is_file($app->environmentPath().'/.env.testing'),
                self::passwordWasInherited()
            );
        });

        $app->afterBootstrapping(LoadEnvironmentVariables::class, function (): void {
            self::$loadedPasswordFingerprint = self::environmentDefined('DB_PASSWORD')
                ? self::passwordFingerprint()
                : null;
        });

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            self::assertSafe($app);
        });
    }

    public static function checkEnvironment(bool $testingFileExists, bool $passwordAlreadySet): void
    {
        if (! $testingFileExists || $passwordAlreadySet) {
            throw new RuntimeException('テスト専用の環境ファイルと環境変数を確認してください。');
        }
    }

    public static function assertSafe(Application $app): void
    {
        $name = $app['config']->get('database.default');
        $connection = is_string($name)
            ? $app['config']->get('database.connections.'.$name)
            : null;

        self::check(
            $connection,
            $app->configurationIsCached(),
            self::environmentValue('DATABASE_URL'),
            self::environmentValue('DB_URL'),
            self::parallelRequested()
        );
    }

    public static function check($connection, bool $cached, $databaseUrl, $dbUrl, bool $parallel): void
    {
        if ($cached) {
            throw new RuntimeException('テスト実行時は設定キャッシュを使用できません。');
        }

        if ($parallel) {
            throw new RuntimeException('並列テストは安全な専用DBが確認されるまで使用できません。');
        }

        if (self::hasValue($databaseUrl) || self::hasValue($dbUrl)
            || ! is_array($connection) || self::hasValue($connection['url'] ?? null)) {
            throw new RuntimeException('テストDBの接続設定を確認してください。');
        }

        if (($connection['driver'] ?? null) !== 'mysql'
            || ($connection['host'] ?? null) !== 'mysql'
            || (string) ($connection['port'] ?? '') !== '3306'
            || ($connection['database'] ?? null) !== 'testing'
            || ($connection['username'] ?? null) !== 'attendance_test'
            || self::hasValue($connection['unix_socket'] ?? null)
            || ! empty($connection['read']) || ! empty($connection['write'])
            || ! empty($connection['options'])) {
            throw new RuntimeException('テスト専用MySQLの接続設定を確認してください。');
        }
    }

    private static function hasValue($value): bool
    {
        return $value !== null && $value !== false && $value !== '';
    }

    private static function environmentValue(string $name): bool
    {
        return self::hasValue($_ENV[$name] ?? null)
            || self::hasValue($_SERVER[$name] ?? null)
            || self::hasValue(getenv($name));
    }

    private static function environmentDefined(string $name): bool
    {
        return array_key_exists($name, $_ENV)
            || array_key_exists($name, $_SERVER)
            || getenv($name) !== false;
    }

    private static function passwordWasInherited(): bool
    {
        if (! self::environmentDefined('DB_PASSWORD')) {
            return false;
        }

        return self::$loadedPasswordFingerprint === null
            || ! hash_equals(self::$loadedPasswordFingerprint, self::passwordFingerprint());
    }

    private static function passwordFingerprint(): string
    {
        return hash('sha256', serialize([
            $_ENV['DB_PASSWORD'] ?? null,
            $_SERVER['DB_PASSWORD'] ?? null,
            getenv('DB_PASSWORD'),
        ]));
    }

    private static function parallelRequested(): bool
    {
        if (self::environmentValue('LARAVEL_PARALLEL_TESTING')
            || self::environmentValue('TEST_TOKEN')) {
            return true;
        }

        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if ($arg === '--parallel' || $arg === '-p' || str_starts_with($arg, '--parallel=')) {
                return true;
            }
        }

        return false;
    }
}
