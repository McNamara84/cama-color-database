<?php

declare(strict_types=1);

namespace Cama\Infrastructure;

use RuntimeException;

final class Runtime
{
    public static function prepare(string $path): void
    {
        foreach (['cache', 'log', 'sessions'] as $directory) {
            $target = $path . '/' . $directory;
            if ((!is_dir($target) && !@mkdir($target, 0700, true)) || !is_writable($target)) {
                throw new RuntimeException('Runtime directory is not writable.');
            }
        }
    }
}
