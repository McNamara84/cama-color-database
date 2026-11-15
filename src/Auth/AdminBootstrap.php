<?php

declare(strict_types=1);

namespace Cama\Auth;

final readonly class AdminBootstrap
{
    public function __construct(private Users $users, private Passwords $passwords)
    {
    }

    public function run(string $email, string $password): User
    {
        $existing = $this->users->admin();
        if ($existing !== null) {
            return $existing;
        }
        $input = ['email' => $email, 'name' => 'Administrator', 'password' => $password, 'password_confirmation' => $password];
        if ((new AccountInput())->errors($input, 'create') !== []) {
            throw new \InvalidArgumentException('Set a valid ADMIN_EMAIL and ADMIN_PASSWORD (15–128 characters) in the private configuration.');
        }
        try {
            return $this->users->create(AccountInput::email($email), 'Administrator', $this->passwords->hash($password), true);
        } catch (DuplicateAccount $error) {
            // A concurrent installer may already have created the single admin slot.
            return $this->users->admin() ?? throw $error;
        }
    }
}
