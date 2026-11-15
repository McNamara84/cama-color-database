<?php

declare(strict_types=1);

namespace Cama\Tests\Integration;

use Cama\Config\Settings;
use Cama\Infrastructure\Database;
use LogicException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseTest extends TestCase
{
    private Database $database;

    protected function setUp(): void
    {
        $this->database = new Database(Settings::load(dirname(__DIR__, 2))->database);
        $pdo = $this->database->connection();
        $pdo->exec('DROP TABLE IF EXISTS fixture_entry');
        $pdo->exec('DROP TABLE IF EXISTS fixture_group');
        $pdo->exec('CREATE TABLE fixture_group (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL UNIQUE) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE fixture_entry (id INT AUTO_INCREMENT PRIMARY KEY, fixture_group_id INT NOT NULL, name VARCHAR(50) NOT NULL, FOREIGN KEY (fixture_group_id) REFERENCES fixture_group(id)) ENGINE=InnoDB');
    }

    protected function tearDown(): void
    {
        $pdo = $this->database->connection();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('DROP TABLE IF EXISTS fixture_entry');
        $pdo->exec('DROP TABLE IF EXISTS fixture_group');
    }

    public function testNotOrmCrudRelationsAndBoundValues(): void
    {
        $orm = $this->database->orm();
        $group = $orm->fixture_group()->insert(['name' => 'Grün 🎨']);
        $entry = $orm->fixture_entry()->insert(['fixture_group_id' => $group['id'], 'name' => "x' OR 1=1 --"]);
        self::assertSame('Grün 🎨', $entry->fixture_group['name']);
        self::assertSame(1, count($group->fixture_entry()));
        self::assertSame(0, count($orm->fixture_entry()->where('name', "' OR 1=1 --")));
        self::assertSame(1, $entry->update(['name' => 'changed']));
        self::assertSame('changed', $orm->fixture_entry()->where('id', $entry['id'])->fetch()['name']);
        self::assertSame(1, $entry->delete());
        self::assertSame(0, count($orm->fixture_entry()));
        self::assertSame($this->database->connection(), $this->database->connection());
    }

    public function testCommitAndRollback(): void
    {
        self::assertSame('committed', $this->database->transaction(static function (PDO $pdo): string {
            $pdo->exec("INSERT INTO fixture_group (name) VALUES ('kept')");
            return 'committed';
        }));
        try {
            $this->database->transaction(static function (PDO $pdo): void {
                $pdo->exec("INSERT INTO fixture_group (name) VALUES ('discarded')");
                throw new RuntimeException('Abort');
            });
            self::fail('Transaction should throw');
        } catch (RuntimeException $error) {
            self::assertSame('Abort', $error->getMessage());
        }
        self::assertSame(['kept'], $this->database->connection()->query('SELECT name FROM fixture_group')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testNestedTransactionIsRejectedWithoutCommittingOuter(): void
    {
        $pdo = $this->database->connection();
        $pdo->beginTransaction();
        try {
            $this->expectException(LogicException::class);
            $this->database->transaction(static fn (): int => 1);
        } finally {
            self::assertTrue($pdo->inTransaction());
            $pdo->rollBack();
        }
    }

    public function testDatabaseConstraintsAreEffective(): void
    {
        $this->expectException(PDOException::class);
        $this->database->orm()->fixture_entry()->insert(['fixture_group_id' => 999, 'name' => 'orphan']);
    }

    public function testAlreadyAbortedTransactionPreservesOriginalError(): void
    {
        $this->expectExceptionMessage('Original failure');
        $this->database->transaction(static function (PDO $pdo): never {
            $pdo->rollBack();
            throw new RuntimeException('Original failure');
        });
    }
}
