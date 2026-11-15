<?php

declare(strict_types=1);

namespace Cama\Infrastructure;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** Copies a Plesk working directory into an immutable, verified release. */
final class StagedRelease
{
    public function install(string $source, string $base, string $id): string
    {
        ReleaseManager::validateId($id);
        $manifest = @file_get_contents($source . '/MANIFEST.sha256');
        if ($manifest === false || $manifest === '') {
            throw new RuntimeException('The deployment manifest is missing.');
        }
        $files = [];
        foreach (explode("\n", rtrim($manifest, "\n")) as $line) {
            if (!preg_match('~^([a-f0-9]{64})  ([a-zA-Z0-9_@+./-]+)$~D', $line, $match)) {
                throw new RuntimeException('Invalid deployment manifest.');
            }
            [$line, $hash, $path] = $match;
            $parts = explode('/', $path);
            if (array_intersect($parts, ['', '.', '..', '.env', '.git']) !== [] || $path === 'MANIFEST.sha256' || isset($files[$path])) {
                throw new RuntimeException('Unsafe or duplicate manifest path.');
            }
            $files[$path] = $hash;
        }
        foreach (['RELEASE', 'public/index.php', 'vendor/autoload.php', 'bin/activate.php', 'bin/composer.phar'] as $required) {
            if (!isset($files[$required])) {
                throw new RuntimeException('Required release file is absent from manifest.');
            }
        }
        $files['MANIFEST.sha256'] = hash('sha256', $manifest);
        if (trim((string) @file_get_contents($source . '/RELEASE')) !== $id) {
            throw new RuntimeException('Staged release identity does not match.');
        }
        $directory = $base . '/releases';
        if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0755))) {
            throw new RuntimeException('Cannot prepare release storage.');
        }
        $release = $directory . '/' . $id;
        if (is_link($release)) {
            throw new RuntimeException('Release directory must not be a symlink.');
        }
        if (file_exists($release)) {
            foreach ($files as $path => $hash) {
                $this->verifyFile($release, $path, $hash);
            }
            return $release;
        }
        $temporary = $directory . '/.incoming-' . bin2hex(random_bytes(8));
        if (!@mkdir($temporary, 0755)) {
            throw new RuntimeException('Cannot create temporary release.');
        }
        try {
            foreach ($files as $path => $hash) {
                $this->verifyFile($source, $path, $hash);
                $parent = dirname($temporary . '/' . $path);
                if (!is_dir($parent) && !@mkdir($parent, 0755, true)) {
                    throw new RuntimeException('Cannot create release subdirectory.');
                }
                if (!@copy($source . '/' . $path, $temporary . '/' . $path)) {
                    throw new RuntimeException('Cannot copy release file.');
                }
                // Detect concurrent Plesk updates between reading the manifest and copying.
                $this->verifyFile($temporary, $path, $hash);
            }
            if (!@rename($temporary, $release)) {
                throw new RuntimeException('Cannot finalize release directory.');
            }
        } finally {
            if (is_dir($temporary)) {
                $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($entries as $entry) {
                    if ($entry->isDir()) {
                        rmdir($entry->getPathname());
                    } else {
                        unlink($entry->getPathname());
                    }
                }
                rmdir($temporary);
            }
        }

        return $release;
    }

    private function verifyFile(string $root, string $path, string $hash): void
    {
        $file = $root . '/' . $path;
        if (realpath($file) !== $file || !is_file($file) || @hash_file('sha256', $file) !== $hash) {
            throw new RuntimeException('Release file is missing, linked or has an unexpected checksum: ' . $path);
        }
    }
}
