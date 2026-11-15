<?php

declare(strict_types=1);

namespace Cama\Auth;

final class Passwords
{
    private const OPTIONS = ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1];

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, self::OPTIONS);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::OPTIONS);
    }
}
