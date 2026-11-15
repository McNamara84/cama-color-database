<?php

declare(strict_types=1);

namespace Cama\Http;

use RuntimeException;

final readonly class Session
{
    public const IDLE_SECONDS = 1800;
    public const MAX_SECONDS = 43200;

    public function __construct(private string $path, private bool $secure)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (!@session_start([
            'save_path' => $this->path,
            'name' => 'cama_session',
            'use_strict_mode' => 1,
            'use_only_cookies' => 1,
            'cookie_secure' => $this->secure,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_path' => '/',
            'cookie_lifetime' => 0,
            'gc_maxlifetime' => self::MAX_SECONDS,
        ])) {
            throw new RuntimeException('Session could not be started.');
        }
    }

    public function csrfToken(): string
    {
        $this->start();
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function login(int $userId, string $credential, ?int $now = null): void
    {
        $this->start();
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Session could not be renewed.');
        }
        $_SESSION = ['user_id' => $userId, 'credential' => $credential, 'created' => $now ?? time(), 'seen' => $now ?? time()];
    }

    /** @return array{id: int, credential: string}|null */
    public function identity(?int $now = null): ?array
    {
        if (session_id() === '' && !isset($_COOKIE['cama_session'])) {
            return null;
        }
        $this->start();
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        $now ??= time();
        if (!is_int($_SESSION['user_id']) || !is_string($_SESSION['credential'] ?? null)
            || !is_int($_SESSION['created'] ?? null) || !is_int($_SESSION['seen'] ?? null)
            || $now - $_SESSION['seen'] >= self::IDLE_SECONDS || $now - $_SESSION['created'] >= self::MAX_SECONDS) {
            $this->logout();
            return null;
        }
        $_SESSION['seen'] = $now;
        return ['id' => $_SESSION['user_id'], 'credential' => $_SESSION['credential']];
    }

    public function logout(): void
    {
        $this->start();
        $_SESSION = [];
        session_destroy();
        session_id('');
        setcookie('cama_session', '', ['expires' => 1, 'path' => '/', 'secure' => $this->secure, 'httponly' => true, 'samesite' => 'Lax']);
    }
}
