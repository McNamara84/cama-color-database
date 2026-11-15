<?php

declare(strict_types=1);

use Cama\Auth\AdminBootstrap;
use Cama\Auth\Passwords;
use Cama\Auth\Users;
use Cama\Config\Settings;
use Cama\Infrastructure\Database;
use Cama\Infrastructure\Runtime;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    if (PHP_SAPI !== 'cli' || !isset($argv) || count($argv) > 2 || (isset($argv[1]) && $argv[1] !== '--bootstrap-only')) {
        throw new RuntimeException('Usage: install.php [--bootstrap-only]');
    }
    if (!in_array('argon2id', password_algos(), true)) {
        throw new RuntimeException('PHP must support Argon2id.');
    }
    $root = dirname(__DIR__);
    $settings = Settings::load($root);
    Runtime::prepare($settings->runtime);
    $lock = fopen($settings->runtime . '/install.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another installation is running.');
    }
    if (!isset($argv[1])) {
        $manager = new Manager(new Config(require $root . '/phinx.php'), new ArrayInput([]), new NullOutput());
        $manager->migrate($settings->environment);
    }
    $value = static function (string $key): string {
        $value = getenv($key);
        return $value !== false ? $value : (is_string($_ENV[$key] ?? null) ? $_ENV[$key] : '');
    };
    (new AdminBootstrap(new Users(new Database($settings->database)), new Passwords()))->run($value('ADMIN_EMAIL'), $value('ADMIN_PASSWORD'));
    echo "Account schema and administrator are ready. Existing credentials were preserved.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Account installation failed (' . $error::class . "). Check migrations, database access, Argon2id support and the private ADMIN_EMAIL / ADMIN_PASSWORD configuration (15–128 characters).\n");
    exit(1);
}
