<?php

declare(strict_types=1);

namespace Cama\Auth;

final readonly class User
{
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
        public string $passwordHash,
        public bool $isAdmin,
        public bool $mustChangePassword,
    ) {
    }

    public function credential(): string
    {
        return hash('sha256', $this->passwordHash);
    }
}
