<?php

declare(strict_types=1);

namespace Cama\Tests;

use Cama\Config\Settings;

final class TestSettings
{
    /** @param array<string, string> $overrides */
    public static function create(array $overrides = []): Settings
    {
        return Settings::fromArray(array_merge([
            'APP_ENV' => 'test', 'APP_DEBUG' => 'false',
            'APP_RUNTIME_DIR' => dirname(__DIR__) . '/var/test',
            'DB_HOST' => 'db', 'DB_NAME' => 'cama_test', 'DB_USER' => 'cama_test',
            'DB_PASSWORD' => (string) getenv('DB_PASSWORD'),
        ], $overrides), dirname(__DIR__));
    }
}
