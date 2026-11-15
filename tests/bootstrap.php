<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'cama_test' || getenv('DB_USER') !== 'cama_test') {
    throw new RuntimeException('Tests require APP_ENV=test and the isolated cama_test database/user. Use docker compose run --rm test.');
}
date_default_timezone_set('UTC');
