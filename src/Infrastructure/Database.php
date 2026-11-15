<?php

declare(strict_types=1);

namespace Cama\Infrastructure;

use Cama\Config\DatabaseConfig;
use LogicException;
use NotORM\NotORM;
use PDO;
use Throwable;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(private readonly DatabaseConfig $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection === null) {
            $this->connection = new PDO($this->config->dsn(), $this->config->user, $this->config->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->connection->exec("SET time_zone = '+00:00'");
            $this->connection->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }

        return $this->connection;
    }

    public function orm(): NotORM
    {
        return new NotORM($this->connection());
    }

    /**
     * @template T
     * @param callable(PDO): T $operation
     * @return T
     */
    public function transaction(callable $operation): mixed
    {
        $pdo = $this->connection();
        if ($pdo->inTransaction()) {
            throw new LogicException('Nested transactions are not supported.');
        }
        $pdo->beginTransaction();
        try {
            $result = $operation($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $error) {
            $this->rollbackIfActive($pdo);
            throw $error;
        }
    }

    private function rollbackIfActive(PDO $pdo): void
    {
        // The callback may have changed the transaction state (e.g. after a deadlock).
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
