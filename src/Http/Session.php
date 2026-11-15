<?php

declare(strict_types=1);

namespace Cama\Http;

use RuntimeException;

final readonly class Session
{
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
}
