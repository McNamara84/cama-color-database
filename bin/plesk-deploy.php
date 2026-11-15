<?php

declare(strict_types=1);

// Plesk deploys the artifact branch to BASE/deploy and invokes this CLI hook.
if (PHP_SAPI !== 'cli' || !isset($argv) || count($argv) !== 2) {
    fwrite(STDERR, "Usage: plesk-deploy.php HTTPS_ORIGIN\n");
    exit(1);
}
$source = dirname(__DIR__);
$id = trim((string) @file_get_contents($source . '/RELEASE'));
$argv = [__DIR__ . '/activate.php', dirname($source), $id, $argv[1], $source];
require __DIR__ . '/activate.php';
