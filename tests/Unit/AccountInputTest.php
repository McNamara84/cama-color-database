<?php

declare(strict_types=1);

namespace Cama\Tests\Unit;

use Cama\Auth\AccountInput;
use Cama\Tests\AccountDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountInputTest extends TestCase
{
    public function testValidLongUnicodePassphrasesAreNotTrimmedOrTruncated(): void
    {
        $password = str_repeat('🎨 ', 32);
        self::assertSame([], (new AccountInput())->errors(['name' => ' Name ', 'email' => ' User@Example.org ', 'password' => $password, 'password_confirmation' => $password, '_csrf' => 'token'], 'create'));
        self::assertSame('user@example.org', AccountInput::email(' User@Example.org '));
    }

    /** @return iterable<string, array{string, int}> */
    public static function unicodePasswordLengths(): iterable
    {
        foreach (['login', 'create', 'password'] as $form) {
            foreach ([100, 128, 129] as $length) {
                yield $form . ' with ' . $length . ' emoji' => [$form, $length];
            }
        }
    }

    #[DataProvider('unicodePasswordLengths')]
    public function testPasswordLimitsCountUnicodeCodePoints(string $form, int $length): void
    {
        $password = str_repeat('🎨', $length);
        $input = $form === 'password' ? ['current_password' => $password] : ['email' => 'user@example.org'];
        $input['password'] = $password;
        if ($form !== 'login') {
            $input['password_confirmation'] = $password;
        }
        if ($form === 'create') {
            $input['name'] = 'Name';
        }
        $errors = (new AccountInput())->errors($input, $form);
        $fields = $form === 'password' ? ['[current_password]', '[password]', '[password_confirmation]'] : ($form === 'create' ? ['[password]', '[password_confirmation]'] : ['[password]']);
        self::assertSame($length > 128 ? $fields : [], array_column($errors, 'field'));
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidInputs(): iterable
    {
        $valid = ['email' => 'user@example.org', 'name' => 'Name', 'password' => AccountDatabase::PASSWORD, 'password_confirmation' => AccountDatabase::PASSWORD];
        yield 'scalar body' => ['string'];
        yield 'missing' => [[]];
        foreach ([
            'email' => ['invalid', ['user@example.org'], str_repeat('a', 255) . '@example.org', "x\0@example.org", 'ü@example.org'],
            'name' => ['', ['name'], str_repeat('a', 101), "x\0name"],
            'password' => ['short', str_repeat('a', 129), ['password'], "null-byte\0password", "invalid\xfflongpassword"],
            'password_confirmation' => ['different long password', null],
        ] as $field => $values) {
            foreach ($values as $i => $value) {
                yield $field . $i => [array_replace($valid, [$field => $value])];
            }
        }
        yield 'role injection' => [$valid + ['admin_slot' => '1']];
    }

    #[DataProvider('invalidInputs')]
    public function testMalformedAccountDataIsRejected(mixed $input): void
    {
        self::assertNotEmpty((new AccountInput())->errors($input, 'create'));
    }
}
