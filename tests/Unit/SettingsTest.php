<?php

declare(strict_types=1);

namespace Cama\Tests\Unit;

use Cama\Config\DatabaseConfig;
use Cama\Config\Settings;
use Cama\Infrastructure\Runtime;
use Cama\Tests\TestSettings;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SettingsTest extends TestCase
{
    public function testDefaultsAndEnvironmentLoading(): void
    {
        $settings = TestSettings::create();
        self::assertFalse($settings->debug);
        self::assertSame('CAMA Color Database', $settings->name);
        self::assertSame('mysql:host=db;port=3306;dbname=cama_test;charset=utf8mb4', $settings->database->dsn());
        self::assertSame('test', Settings::load(dirname(__DIR__, 2))->environment);
        self::assertSame('cama_test', Settings::load(dirname(__DIR__, 2), true)->database->user);
        self::assertTrue(TestSettings::create(['APP_ENV' => 'development', 'APP_DEBUG' => 'true'])->debug);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidSettings(): iterable
    {
        foreach (['APP_ENV' => 'unknown', 'APP_DEBUG' => 'yes', 'APP_RUNTIME_DIR' => 'relative', 'DB_HOST' => 'db;dbname=other', 'DB_PORT' => '33.0', 'DB_NAME' => 'a;b', 'DB_USER' => '', 'DB_PASSWORD' => 'CHANGE_ME_APP'] as $key => $value) {
            yield $key => [[$key => $value]];
        }
        yield 'production debug' => [['APP_ENV' => 'production', 'APP_DEBUG' => 'true']];
        yield 'port low' => [['DB_PORT' => '0']];
        yield 'port high' => [['DB_PORT' => '65536']];
        yield 'null path' => [['APP_RUNTIME_DIR' => "/tmp/a\0b"]];
        yield 'empty password' => [['DB_PASSWORD' => '']];
    }

    #[DataProvider('invalidSettings')]
    public function testRejectsInvalidSettings(array $overrides): void
    {
        $this->expectException(InvalidArgumentException::class);
        TestSettings::create($overrides);
    }

    public function testMissingCredentialsFailClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Settings::fromArray([], '/app');
    }

    public function testDotenvFallbackAndProcessPrecedence(): void
    {
        $path = sys_get_temp_dir() . '/cama-env-' . bin2hex(random_bytes(8));
        mkdir($path);
        file_put_contents($path . '/.env', 'APP_NAME="File name"');
        $original = getenv('APP_NAME');
        $environment = $_ENV['APP_NAME'] ?? null;
        $server = $_SERVER['APP_NAME'] ?? null;
        putenv('APP_NAME');
        unset($_ENV['APP_NAME'], $_SERVER['APP_NAME']);
        try {
            self::assertSame('File name', Settings::load($path)->name);
            putenv('APP_NAME=Process name');
            self::assertSame('Process name', Settings::load($path)->name);
        } finally {
            $original === false ? putenv('APP_NAME') : putenv('APP_NAME=' . $original);
            if ($environment === null) {
                unset($_ENV['APP_NAME']);
            } else {
                $_ENV['APP_NAME'] = $environment;
            }
            if ($server === null) {
                unset($_SERVER['APP_NAME']);
            } else {
                $_SERVER['APP_NAME'] = $server;
            }
            unlink($path . '/.env');
            rmdir($path);
        }
    }

    public function testRuntimeDirectoriesArePrivateAndIdempotent(): void
    {
        $path = sys_get_temp_dir() . '/cama-runtime-' . bin2hex(random_bytes(8));
        Runtime::prepare($path);
        Runtime::prepare($path);
        foreach (['cache', 'log', 'sessions'] as $dir) {
            self::assertDirectoryIsWritable($path . '/' . $dir);
            self::assertSame(0700, fileperms($path . '/' . $dir) & 0777);
            rmdir($path . '/' . $dir);
        }
        rmdir($path);
    }

    public function testInvalidRuntimePathFails(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cama-');
        try {
            $this->expectException(RuntimeException::class);
            Runtime::prepare($file);
        } finally {
            unlink($file);
        }
    }
}
