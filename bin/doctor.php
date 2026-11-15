<?php

declare(strict_types=1);

use Cama\Config\Settings;
use Cama\Infrastructure\Database;
use Cama\Infrastructure\Runtime;

require dirname(__DIR__) . '/vendor/autoload.php';
try {
    $settings = Settings::load(dirname(__DIR__));
    Runtime::prepare($settings->runtime);
    $db = (new Database($settings->database))->connection();
    $query = $db->query('SELECT VERSION()');
    if ($query === false) {
        throw new RuntimeException('Database check failed.');
    }
    $version = $query->fetchColumn();
    echo 'PHP ' . PHP_VERSION . '; Database ' . $version . '; environment ' . $settings->environment . ": OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Configuration/database/runtime check failed (' . $error::class . "). Check settings and permissions.\n");
    exit(1);
}
