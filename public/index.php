<?php

declare(strict_types=1);

use Cama\AppFactory;
use Cama\Config\Settings;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    AppFactory::create(Settings::load(dirname(__DIR__)))->run();
} catch (Throwable $error) {
    error_log('CAMA bootstrap failed: ' . $error::class);
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Die Anwendung ist vorübergehend nicht verfügbar.';
}
