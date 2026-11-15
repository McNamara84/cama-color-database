<?php

declare(strict_types=1);

namespace Cama\Tests\Unit;

use Cama\Validation\FormValidator;
use Cama\Validation\InputParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;

final class ValidationTest extends TestCase
{
    public function testExplicitParsingPreservesZeroAndFalse(): void
    {
        self::assertSame(0, InputParser::integer('0'));
        self::assertSame(-12, InputParser::integer('-12'));
        self::assertSame(PHP_INT_MAX, InputParser::integer((string) PHP_INT_MAX));
        self::assertSame(12, InputParser::integer(12));
        self::assertFalse(InputParser::boolean('0'));
        self::assertFalse(InputParser::boolean(false));
        self::assertTrue(InputParser::boolean('1'));
        self::assertTrue(InputParser::boolean(true));
        self::assertSame('2024-02-29 00:00:00', InputParser::date('2024-02-29')->format('Y-m-d H:i:s'));
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidValues(): iterable
    {
        foreach (['', null, [], true, 1.0, ' 1', '1 ', '01', '+1', '1e3', '1.1', "1\n", '999999999999999999999999'] as $i => $value) {
            yield 'integer ' . $i => ['integer', $value];
        }
        foreach (['false', 'true', '', null, [], 0, 1, 'yes'] as $i => $value) {
            yield 'boolean ' . $i => ['boolean', $value];
        }
        foreach (['2025-02-29', '2026-13-01', '2026-04-31', '15.11.2026', '2026-1-01', '', null, [], "2026-01-01\n"] as $i => $value) {
            yield 'date ' . $i => ['date', $value];
        }
    }

    #[DataProvider('invalidValues')]
    public function testRejectsAmbiguousAndInvalidValues(string $parser, mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        InputParser::$parser($value);
    }

    public function testFormShapeConstraintsAndStableErrorCodes(): void
    {
        $validator = new FormValidator();
        $fields = ['name' => new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(), new Assert\Length(max: 5)])];
        self::assertSame([], $validator->errors(['name' => 'Grün'], $fields));
        foreach ([[], ['name' => ''], ['name' => null], ['name' => []], ['name' => 'too long'], ['name' => 'ok', 'admin' => true]] as $input) {
            $errors = $validator->errors($input, $fields);
            self::assertNotEmpty($errors);
            self::assertNotEmpty($errors[0]['code']);
            self::assertNotEmpty($errors[0]['message']);
            self::assertStringStartsWith('[', $errors[0]['field']);
        }
        self::assertSame('invalid_structure', $validator->errors('name=value', $fields)[0]['code']);
    }
}
