<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Php\Pie\Platform\InstalledPiePackages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstalledPiePackages::class)]
final class InstalledPiePackagesTest extends TestCase
{
    public function testAllPiePackages(): void
    {
        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('getPackages')->willReturn([
            new CompletePackage('foo/bar1', '1.2.3.0', '1.2.3'),
            new CompletePackage('foo/bar2', '1.2.3.0', '1.2.3'),
        ]);

        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getLocalRepository')->willReturn($localRepo);

        $composer = $this->createMock(Composer::class);
        $composer->method('getRepositoryManager')->willReturn($repoManager);

        $packages = (new InstalledPiePackages())->allPiePackages($composer)->packages();

        self::assertCount(2, $packages);

        self::assertSame('bar1', $packages[0]->extensionName()->name());
        self::assertSame('foo/bar1', $packages[0]->name());
        self::assertSame('bar2', $packages[1]->extensionName()->name());
        self::assertSame('foo/bar2', $packages[1]->name());
    }

    public function testInvalidExtensionNamesAreFilteredOut(): void
    {
        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('getPackages')->willReturn([
            new CompletePackage('foo/invalid-extension-name', '1.2.3.0', '1.2.3'),
            new CompletePackage('invalid-extension-name', '1.2.3.0', '1.2.3'),
            new CompletePackage('invalid_extension_name', '1.2.3.0', '1.2.3'),
        ]);

        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getLocalRepository')->willReturn($localRepo);

        $composer = $this->createMock(Composer::class);
        $composer->method('getRepositoryManager')->willReturn($repoManager);

        self::assertCount(0, (new InstalledPiePackages())->allPiePackages($composer));
    }
}
