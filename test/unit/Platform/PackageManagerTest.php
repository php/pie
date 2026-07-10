<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Composer\IO\BufferIO;
use Php\Pie\Platform\PackageManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

#[CoversClass(PackageManager::class)]
final class PackageManagerTest extends TestCase
{
    public function testInstallCommand(): void
    {
        self::assertSame(
            ['echo', '"fake installing a, b"'],
            PackageManager::Test->installCommand(['a', 'b']),
        );
        self::assertSame(
            ['apt-get', 'install', '-y', '--no-install-recommends', '--no-install-suggests', 'a', 'b'],
            PackageManager::Apt->installCommand(['a', 'b']),
        );
        self::assertSame(
            ['apk', 'add', '--no-cache', '--virtual', '.php-pie-deps', 'a', 'b'],
            PackageManager::Apk->installCommand(['a', 'b']),
        );
        self::assertSame(
            ['dnf', 'install', '-y', 'a', 'b'],
            PackageManager::Dnf->installCommand(['a', 'b']),
        );
        self::assertSame(
            ['yum', 'install', '-y', 'a', 'b'],
            PackageManager::Yum->installCommand(['a', 'b']),
        );
        self::assertSame(
            ['brew', 'install', 'a', 'b'],
            PackageManager::Brew->installCommand(['a', 'b']),
        );
    }

    public function testInstallWithYumWritesDeprecationWarning(): void
    {
        $io = new BufferIO();

        try {
            PackageManager::Yum->install($io, ['some-package-that-does-not-exist']);
        } catch (ProcessFailedException) {
            // Expected
        }

        self::assertStringContainsString('deprecated', $io->getOutput());
    }
}
