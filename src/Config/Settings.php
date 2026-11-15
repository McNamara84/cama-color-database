<?php

declare(strict_types=1);

namespace Cama\Config;

use Dotenv\Dotenv;
use InvalidArgumentException;

final readonly class Settings
{
    public function __construct(
        public string $root,
        public string $environment,
        public bool $debug,
        public string $name,
        public string $runtime,
        public DatabaseConfig $database,
    ) {
    }

    public static function load(string $root, bool $migration = false): self
    {
        Dotenv::createImmutable($root)->safeLoad();
        $values = [];
        foreach (['APP_ENV', 'APP_DEBUG', 'APP_NAME', 'APP_RUNTIME_DIR', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_MIGRATION_USER', 'DB_MIGRATION_PASSWORD'] as $key) {
            $value = getenv($key);
            if ($value === false) {
                $value = $_ENV[$key] ?? null;
            }
            if (is_string($value)) {
                $values[$key] = $value;
            }
        }
        if ($migration) {
            $values['DB_USER'] = $values['DB_MIGRATION_USER'] ?? '';
            $values['DB_PASSWORD'] = $values['DB_MIGRATION_PASSWORD'] ?? '';
        }

        return self::fromArray($values, $root);
    }

    /** @param array<string, string> $values */
    public static function fromArray(array $values, string $root): self
    {
        $environment = $values['APP_ENV'] ?? 'production';
        if (!in_array($environment, ['production', 'development', 'test'], true)) {
            throw new InvalidArgumentException('APP_ENV must be production, development or test.');
        }
        $debug = $values['APP_DEBUG'] ?? 'false';
        if (!in_array($debug, ['true', 'false'], true) || ($environment === 'production' && $debug !== 'false')) {
            throw new InvalidArgumentException('APP_DEBUG must be true/false and false in production.');
        }
        $runtime = $values['APP_RUNTIME_DIR'] ?? $root . '/var';
        if (!str_starts_with($runtime, '/') || str_contains($runtime, "\0")) {
            throw new InvalidArgumentException('APP_RUNTIME_DIR must be an absolute path.');
        }
        $port = $values['DB_PORT'] ?? '3306';
        if (!ctype_digit($port)) {
            throw new InvalidArgumentException('DB_PORT must be an integer.');
        }

        return new self(
            $root,
            $environment,
            $debug === 'true',
            $values['APP_NAME'] ?? 'CAMA Color Database',
            $runtime,
            new DatabaseConfig($values['DB_HOST'] ?? '', (int) $port, $values['DB_NAME'] ?? '', $values['DB_USER'] ?? '', $values['DB_PASSWORD'] ?? '')
        );
    }
}
