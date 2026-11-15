<?php

declare(strict_types=1);

use Cama\Config\Settings;
use Cama\Infrastructure\Database;
use Cama\Infrastructure\ReleaseManager;
use Cama\Infrastructure\Runtime;
use Cama\Infrastructure\StagedRelease;

require dirname(__DIR__) . '/vendor/autoload.php';

// This CLI is never exposed through the public document root.
try {
    if (PHP_SAPI !== 'cli' || !isset($argv) || !in_array(count($argv), [4, 5], true)) {
        throw new RuntimeException('Usage: activate.php BASE FULL_GIT_SHA HTTPS_ORIGIN [STAGING_DIRECTORY]');
    }
    [$script, $base, $id, $origin] = $argv;
    if (!str_starts_with($base, '/') || realpath($base) !== $base || !preg_match('~^https://[a-zA-Z0-9.-]+(?::[0-9]+)?$~D', $origin)) {
        throw new RuntimeException('Base must be canonical and origin must be HTTPS without a path.');
    }
    $lock = fopen($base . '/.deploy.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another deployment is active.');
    }
    if (isset($argv[4])) {
        (new StagedRelease())->install($argv[4], $base, $id);
    }
    $manager = new ReleaseManager();
    $release = $manager->prepare($base, $id);
    $settings = Settings::load($release);
    if ($settings->environment !== 'production') {
        throw new RuntimeException('Deployment requires APP_ENV=production.');
    }
    Runtime::prepare($settings->runtime);
    (new Database($settings->database))->connection();
    $commands = [
        [PHP_BINARY, $release . '/bin/composer.phar', 'check-platform-reqs', '--no-dev'],
        [PHP_BINARY, $release . '/vendor/bin/phinx', 'status', '-c', $release . '/phinx.php'],
        [PHP_BINARY, $release . '/bin/install.php', '--bootstrap-only'],
    ];
    foreach ($commands as $command) {
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $release);
        if (!is_resource($process) || proc_close($process) !== 0) {
            throw new RuntimeException('Preflight failed; pending/missing migrations must be handled before deployment.');
        }
    }
    $manager->activate($base, $id, static function () use ($origin, $id): bool {
        $context = stream_context_create(['http' => ['timeout' => 10, 'follow_location' => 0, 'header' => "Cache-Control: no-cache\r\n"]]);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $body = @file_get_contents($origin . '/health?release=' . $id, false, $context);
            $home = @file_get_contents($origin . '/', false, $context);
            $health = $body === false ? null : json_decode($body, true);
            if (is_array($health) && ($health['release'] ?? null) === $id && ($health['status'] ?? null) === 'ok' && is_string($home) && str_contains($home, '<!doctype html>')) {
                return true;
            }
            if ($attempt < 2) {
                sleep(1);
            }
        }
        return false;
    });
    echo "Release verified and activated.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Deployment failed: ' . $error->getMessage() . "\n");
    exit(1);
}
