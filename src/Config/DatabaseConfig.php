<?php

declare(strict_types=1);

namespace Cama\Config;

use InvalidArgumentException;

final readonly class DatabaseConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $name,
        public string $user,
        public string $password,
    ) {
        if (!preg_match('/^[a-zA-Z0-9_.:-]+$/D', $host) || $port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Invalid DB_HOST or DB_PORT.');
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $name) || $user === '' || $password === '' || str_starts_with($password, 'CHANGE_ME')) {
            throw new InvalidArgumentException('DB_NAME, DB_USER and a non-placeholder DB_PASSWORD are required.');
        }
    }

    public function dsn(): string
    {
        return "mysql:host={$this->host};port={$this->port};dbname={$this->name};charset=utf8mb4";
    }
}
