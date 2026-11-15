<?php

declare(strict_types=1);

use Cama\Config\Settings;

require __DIR__ . '/vendor/autoload.php';
$settings = Settings::load(__DIR__, true);
$db = $settings->database;

return [
    'paths' => ['migrations' => __DIR__ . '/migrations'],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => $settings->environment,
        $settings->environment => [
            'adapter' => 'mysql', 'host' => $db->host, 'port' => $db->port,
            'name' => $db->name, 'user' => $db->user, 'pass' => $db->password,
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    'version_order' => 'creation',
];
