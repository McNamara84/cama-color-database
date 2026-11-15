<?php

declare(strict_types=1);

namespace Cama\Auth;

use Cama\Infrastructure\Database;

final readonly class LoginLimiter
{
    public const WINDOW = 900;

    public function __construct(private Database $database)
    {
    }

    public function consume(string $email, string $ip, int $now): bool
    {
        $pdo = $this->database->connection();
        $cleanup = $pdo->prepare('DELETE FROM login_attempts WHERE expires_at <= ? LIMIT 1000');
        $cleanup->execute([$now]);
        $allowed = true;
        foreach (['email:' . $email => 5, 'ip:' . $ip => 30] as $key => $limit) {
            // Atomic increments survive simultaneous requests and different PHP workers.
            $bucket = hash('sha256', $key);
            $write = $pdo->prepare('INSERT INTO login_attempts (bucket, attempts, expires_at) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE attempts = IF(expires_at <= ?, 1, attempts + 1), expires_at = IF(expires_at <= ?, ?, expires_at)');
            $write->execute([$bucket, $now + self::WINDOW, $now, $now, $now + self::WINDOW]);
            $read = $pdo->prepare('SELECT attempts FROM login_attempts WHERE bucket = ?');
            $read->execute([$bucket]);
            $allowed = ((int) $read->fetchColumn() <= $limit) && $allowed;
        }
        return $allowed;
    }

    public function clearEmail(string $email): void
    {
        $query = $this->database->connection()->prepare('DELETE FROM login_attempts WHERE bucket = ?');
        $query->execute([hash('sha256', 'email:' . $email)]);
    }
}
