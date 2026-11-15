<?php

declare(strict_types=1);

namespace Cama\Tests\Unit;

use Cama\Infrastructure\ReleaseManager;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class ReleaseManagerTest extends TestCase
{
    private string $base;
    private string $id;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/cama-deploy-' . bin2hex(random_bytes(8));
        $this->id = str_repeat('a', 40);
        mkdir($this->base . '/shared', 0700, true);
        mkdir($this->base . '/releases/' . $this->id . '/public', 0700, true);
        file_put_contents($this->base . '/shared/.env', 'APP_ENV=production');
        file_put_contents($this->base . '/releases/' . $this->id . '/RELEASE', $this->id);
        file_put_contents($this->base . '/releases/' . $this->id . '/public/index.php', '<?php');
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->base, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->base);
    }

    public function testPreparationIsIdempotentAndActivationVerifiesNewRelease(): void
    {
        $manager = new ReleaseManager();
        $release = $manager->prepare($this->base, $this->id);
        self::assertSame($release, $manager->prepare($this->base, $this->id));
        self::assertSame($this->base . '/shared/.env', readlink($release . '/.env'));
        $manager->activate($this->base, $this->id, function (): bool {
            self::assertSame($this->id, file_get_contents($this->base . '/current/RELEASE'));
            return true;
        });
        self::assertSame('releases/' . $this->id, readlink($this->base . '/current'));
    }

    public function testFailedHealthCheckRestoresPreviousRelease(): void
    {
        symlink('releases/previous', $this->base . '/current');
        try {
            (new ReleaseManager())->activate($this->base, $this->id, static fn (): bool => false);
            self::fail('Activation must fail');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('verification', $error->getMessage());
        }
        self::assertSame('releases/previous', readlink($this->base . '/current'));
    }

    public function testFirstDeploymentFailureLeavesNoCurrentRelease(): void
    {
        try {
            (new ReleaseManager())->activate($this->base, $this->id, static function (): never {
                throw new RuntimeException('network');
            });
            self::fail('Activation must fail');
        } catch (RuntimeException $error) {
            self::assertSame('network', $error->getMessage());
        }
        self::assertFalse(is_link($this->base . '/current'));
    }

    public function testExistingDirectoryIsNotOverwritten(): void
    {
        mkdir($this->base . '/current');
        $this->expectException(RuntimeException::class);
        (new ReleaseManager())->activate($this->base, $this->id, static fn (): bool => true);
    }

    public function testMissingEntryPointIsRejected(): void
    {
        unlink($this->base . '/releases/' . $this->id . '/public/index.php');
        $this->expectException(RuntimeException::class);
        (new ReleaseManager())->activate($this->base, $this->id, static fn (): bool => true);
    }

    /** @return iterable<string, array{string}> */
    public static function badIds(): iterable
    {
        foreach (['', '../current', str_repeat('z', 40), str_repeat('a', 39), str_repeat('a', 40) . "\n"] as $id) {
            yield $id => [$id];
        }
    }

    #[DataProvider('badIds')]
    public function testReleaseIdsCannotTraversePaths(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReleaseManager())->prepare($this->base, $id);
    }

    public function testIdentityMismatchIsRejected(): void
    {
        file_put_contents($this->base . '/releases/' . $this->id . '/RELEASE', 'wrong');
        $this->expectException(RuntimeException::class);
        (new ReleaseManager())->prepare($this->base, $this->id);
    }

    public function testMissingConfigurationIsRejected(): void
    {
        unlink($this->base . '/shared/.env');
        $this->expectException(RuntimeException::class);
        (new ReleaseManager())->prepare($this->base, $this->id);
    }

    public function testBundledSecretsAreRejected(): void
    {
        file_put_contents($this->base . '/releases/' . $this->id . '/.env', 'secret');
        $this->expectException(RuntimeException::class);
        (new ReleaseManager())->prepare($this->base, $this->id);
    }

    public function testUnwritableReleaseFailsBeforeActivation(): void
    {
        $release = $this->base . '/releases/' . $this->id;
        chmod($release, 0500);
        try {
            $this->expectException(RuntimeException::class);
            (new ReleaseManager())->prepare($this->base, $this->id);
        } finally {
            chmod($release, 0700);
            self::assertFalse(is_link($this->base . '/current'));
        }
    }

    public function testUnwritableBasePreservesPreviousRelease(): void
    {
        symlink('releases/previous', $this->base . '/current');
        chmod($this->base, 0500);
        try {
            $this->expectException(RuntimeException::class);
            (new ReleaseManager())->activate($this->base, $this->id, static fn (): bool => true);
        } finally {
            chmod($this->base, 0700);
            self::assertSame('releases/previous', readlink($this->base . '/current'));
        }
    }
}
