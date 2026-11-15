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
