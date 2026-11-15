<?php

declare(strict_types=1);

namespace Cama\Infrastructure;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Filesystem activation only; schema changes must be completed separately. */
final class ReleaseManager
{
    public static function validateId(string $id): void
    {
        if (!preg_match('/^[a-f0-9]{40}$/D', $id)) {
            throw new InvalidArgumentException('Release ID must be a full Git SHA.');
        }
    }

    public function prepare(string $base, string $id): string
    {
        self::validateId($id);
        $release = $base . '/releases/' . $id;
        if (!is_dir($release) || is_link($release) || !is_file($release . '/RELEASE') || trim((string) file_get_contents($release . '/RELEASE')) !== $id) {
            throw new RuntimeException('Release directory or identity is invalid.');
        }
        if (!is_file($base . '/shared/.env')) {
            throw new RuntimeException('Provision shared/.env before deployment.');
        }
        $configuration = '../../shared/.env';
        if (is_link($release . '/.env') && in_array(readlink($release . '/.env'), [$configuration, $base . '/shared/.env'], true)) {
            return $release;
        }
        if (file_exists($release . '/.env') || is_link($release . '/.env')) {
            throw new RuntimeException('Release must not contain an environment file.');
        }
        if (!@symlink($configuration, $release . '/.env')) {
            throw new RuntimeException('Could not attach production configuration.');
        }

        return $release;
    }

    /** @param callable(): bool $verify */
    public function activate(string $base, string $id, callable $verify): void
    {
        self::validateId($id);
        $current = $base . '/current';
        if (file_exists($current) && !is_link($current)) {
            throw new RuntimeException('current must be a symlink; hosting must support atomic symlink activation.');
        }
        $old = is_link($current) ? readlink($current) : null;
        $target = 'releases/' . $id;
        if (!is_file($base . '/' . $target . '/public/index.php')) {
            throw new RuntimeException('Release has no public entry point.');
        }
        $this->switchLink($base, $target);
        try {
            if (!$verify()) {
                throw new RuntimeException('HTTP release verification failed.');
            }
        } catch (Throwable $error) {
            if (is_string($old)) {
                $this->switchLink($base, $old);
            } else {
                unlink($current);
            }
            throw $error;
        }
    }

    private function switchLink(string $base, string $target): void
    {
        $next = $base . '/.next-' . bin2hex(random_bytes(8));
        if (!@symlink($target, $next)) {
            throw new RuntimeException('Cannot create release symlink.');
        }
        if (!@rename($next, $base . '/current')) {
            unlink($next);
            throw new RuntimeException('Cannot atomically activate release.');
        }
        clearstatcache(true);
    }
}
