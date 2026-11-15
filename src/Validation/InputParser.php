<?php

declare(strict_types=1);

namespace Cama\Validation;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class InputParser
{
    public static function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || !preg_match('/^-?(0|[1-9][0-9]*)$/D', $value)) {
            throw new InvalidArgumentException('Expected an integer.');
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false) {
            throw new InvalidArgumentException('Integer is outside the supported range.');
        }

        return $parsed;
    }

    public static function boolean(mixed $value): bool
    {
        return match ($value) {
            true, '1' => true,
            false, '0' => false,
            default => throw new InvalidArgumentException('Expected a boolean or the string 0/1.'),
        };
    }

    public static function date(mixed $value): DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) {
            throw new InvalidArgumentException('Expected an ISO date.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invalid calendar date.');
        }

        return $date;
    }
}
