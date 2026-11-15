<?php

declare(strict_types=1);

namespace Cama\Auth;

use Cama\Infrastructure\Database;
use NotORM\Row;
use PDOException;

final readonly class Users
{
    public function __construct(private Database $database)
    {
    }

    public function byEmail(string $email): ?User
    {
        return $this->user($this->database->orm()->__call('users', [])->where('email', $email)->fetch());
    }

    public function byId(int $id): ?User
    {
        return $this->user($this->database->orm()->__call('users', [])->where('id', $id)->fetch());
    }

    public function admin(): ?User
    {
        return $this->user($this->database->orm()->__call('users', [])->where('admin_slot', 1)->fetch());
    }

    public function create(string $email, string $name, string $hash, bool $admin = false): User
    {
        try {
            $row = $this->database->orm()->__call('users', [])->insert([
                'email' => $email, 'name' => $name, 'password_hash' => $hash,
                'admin_slot' => $admin ? 1 : null, 'must_change_password' => true,
            ]);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) {
                throw new DuplicateAccount('An account already exists.');
            }
            throw $error;
        }

        return $this->user($row) ?? throw new \RuntimeException('Account could not be created.');
    }

    /** @return list<array{email: string, name: string, is_admin: bool}> */
    public function recent(): array
    {
        $result = [];
        foreach ($this->database->orm()->__call('users', [])->select('email, name, admin_slot')->order('id DESC')->limit(50) as $row) {
            $result[] = ['email' => (string) $row['email'], 'name' => (string) $row['name'], 'is_admin' => (int) $row['admin_slot'] === 1];
        }
        return $result;
    }

    public function changePassword(User $user, string $hash, bool $mustChange = false): bool
    {
        return $this->database->orm()->__call('users', [])->where('id', $user->id)->where('password_hash', $user->passwordHash)
            ->update(['password_hash' => $hash, 'must_change_password' => $mustChange]) === 1;
    }

    private function user(mixed $row): ?User
    {
        if (!$row instanceof Row) {
            return null;
        }
        return new User((int) $row['id'], (string) $row['email'], (string) $row['name'], (string) $row['password_hash'], (int) $row['admin_slot'] === 1, (bool) $row['must_change_password']);
    }
}
