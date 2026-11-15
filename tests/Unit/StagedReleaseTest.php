<?php

declare(strict_types=1);

namespace Cama\Tests\Unit;

use Cama\Infrastructure\StagedRelease;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class StagedReleaseTest extends TestCase
{
    private string $base;
    private string $source;
    private string $id;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/cama-plesk-' . bin2hex(random_bytes(8));
        $this->source = $this->base . '/deploy';
        $this->id = str_repeat('a', 40);
        foreach (['public', 'vendor', 'bin'] as $directory) {
            mkdir($this->source . '/' . $directory, 0755, true);
        }
        $files = ['RELEASE' => $this->id, 'public/index.php' => '<?php', 'vendor/autoload.php' => '<?php', 'bin/activate.php' => '<?php', 'bin/composer.phar' => 'composer'];
        $manifest = '';
        foreach ($files as $path => $content) {
            file_put_contents($this->source . '/' . $path, $content);
            $manifest .= hash('sha256', $content) . '  ' . $path . "\n";
        }
        file_put_contents($this->source . '/MANIFEST.sha256', $manifest);
        symlink('releases/previous', $this->base . '/current');
    }

    protected function tearDown(): void
    {
        chmod($this->base, 0755);
        if (is_dir($this->base . '/releases') && !is_link($this->base . '/releases')) {
            chmod($this->base . '/releases', 0755);
        }
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->base, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($entries as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->base);
    }

    public function testImportIsCompleteAndRepeatableWithoutCopyingUnlistedFiles(): void
    {
        file_put_contents($this->source . '/.env', 'must stay outside the release');
        $installer = new StagedRelease();
        $release = $installer->install($this->source, $this->base, $this->id);
        self::assertSame($this->id, file_get_contents($release . '/RELEASE'));
        self::assertFileExists($release . '/vendor/autoload.php');
        self::assertFileDoesNotExist($release . '/.env');
        self::assertSame($release, $installer->install($this->source, $this->base, $this->id));
        self::assertSame('releases/previous', readlink($this->base . '/current'));
        self::assertSame([], glob($this->base . '/releases/.incoming-*'));
    }

    public function testFailedCopyVerificationCleansPartialReleaseAndPreservesCurrent(): void
    {
        file_put_contents($this->source . '/bin/activate.php', 'damaged');
        try {
            (new StagedRelease())->install($this->source, $this->base, $this->id);
            self::fail('Corrupted package must fail.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('checksum', $error->getMessage());
        }
        self::assertDirectoryDoesNotExist($this->base . '/releases/' . $this->id);
        self::assertSame([], glob($this->base . '/releases/.incoming-*'));
        self::assertSame('releases/previous', readlink($this->base . '/current'));
    }

    public function testDifferentPackageCannotOverwriteTheSameRelease(): void
    {
        $installer = new StagedRelease();
        $release = $installer->install($this->source, $this->base, $this->id);
        file_put_contents($release . '/public/index.php', 'modified');
        $this->expectException(RuntimeException::class);
        $installer->install($this->source, $this->base, $this->id);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidManifests(): iterable
    {
        yield 'empty' => [''];
        yield 'malformed' => ['not a checksum'];
        foreach (['../escape', '/absolute', './relative', 'public//file', '.env', '.git/config', 'MANIFEST.sha256', 'a/../../escape'] as $path) {
            yield $path => [str_repeat('a', 64) . '  ' . $path . "\n"];
        }
        yield 'missing required files' => [str_repeat('a', 64) . "  RELEASE\n"];
        yield 'duplicate entry' => [str_repeat(str_repeat('a', 64) . "  RELEASE\n", 2)];
    }

    #[DataProvider('invalidManifests')]
    public function testInvalidManifestIsRejected(string $manifest): void
    {
        file_put_contents($this->source . '/MANIFEST.sha256', $manifest);
        $this->expectException(RuntimeException::class);
        (new StagedRelease())->install($this->source, $this->base, $this->id);
    }

    public function testWrongSourceIdentityIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        (new StagedRelease())->install($this->source, $this->base, str_repeat('b', 40));
    }

    public function testSymlinkInSourceCannotEscapeStaging(): void
    {
        rename($this->source . '/public', $this->base . '/outside');
        symlink($this->base . '/outside', $this->source . '/public');
        $this->expectException(RuntimeException::class);
        (new StagedRelease())->install($this->source, $this->base, $this->id);
    }

    public function testReleaseSymlinkIsRejected(): void
    {
        mkdir($this->base . '/releases');
        symlink($this->source, $this->base . '/releases/' . $this->id);
        $this->expectException(RuntimeException::class);
        (new StagedRelease())->install($this->source, $this->base, $this->id);
    }

    public function testStorageSymlinkIsRejected(): void
    {
        symlink($this->source, $this->base . '/releases');
        $this->expectException(RuntimeException::class);
        (new StagedRelease())->install($this->source, $this->base, $this->id);
    }

    public function testUnwritableBaseIsRejected(): void
    {
        chmod($this->base, 0500);
        $this->expectException(RuntimeException::class);
        (new StagedRelease())->install($this->source, $this->base, $this->id);
    }

    public function testUnwritableStorageIsRejected(): void
    {
        mkdir($this->base . '/releases', 0500);
        $this->expectException(RuntimeException::class);
        (new StagedRelease())->install($this->source, $this->base, $this->id);
    }
}
