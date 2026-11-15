<?php

declare(strict_types=1);

namespace Cama\Tests\Integration;

use Cama\Config\Settings;
use Cama\Infrastructure\Database;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class MigrationTest extends TestCase
{
    public function testMigrationAndRepeatedRunAndRollback(): void
    {
        $root = dirname(__DIR__, 2);
        $configuration = require $root . '/phinx.php';
        $configuration['paths']['migrations'] = $root . '/tests/fixtures/migrations';
        $configuration['environments']['default_migration_table'] = 'fixture_phinxlog';
        $manager = new Manager(new Config($configuration), new ArrayInput([]), new NullOutput());
        $pdo = (new Database(Settings::load($root)->database))->connection();
        $pdo->exec('DROP TABLE IF EXISTS migration_fixture');
        $pdo->exec('DROP TABLE IF EXISTS fixture_phinxlog');
        try {
            $manager->migrate('test');
            $manager->migrate('test');
            self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM fixture_phinxlog')->fetchColumn());
            $pdo->exec("INSERT INTO migration_fixture (label) VALUES ('retained')");
            $manager->migrate('test');
            self::assertSame('retained', $pdo->query('SELECT label FROM migration_fixture')->fetchColumn());
            $manager->rollback('test', 0);
            self::assertSame([], $pdo->query("SHOW TABLES LIKE 'migration_fixture'")->fetchAll());
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS migration_fixture');
            $pdo->exec('DROP TABLE IF EXISTS fixture_phinxlog');
        }
    }
}
