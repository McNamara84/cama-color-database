<?php

declare(strict_types=1);

namespace Cama\Tests;

use Cama\Infrastructure\Database;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class AccountDatabase
{
    public const PASSWORD = 'Test-only long passphrase 123!';

    public static function reset(): Database
    {
        $config = require dirname(__DIR__) . '/phinx.php';
        (new Manager(new Config($config), new ArrayInput([]), new NullOutput()))->migrate('test');
        $database = new Database(TestSettings::create()->database);
        $database->connection()->exec('DELETE FROM login_attempts');
        $database->connection()->exec('DELETE FROM users');
        return $database;
    }
}
