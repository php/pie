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

        $packages = (new InstalledPiePackages())->allPiePackages($composer);

        self::assertArrayHasKey('bar1', $packages);
        self::assertArrayHasKey('bar2', $packages);

        self::assertSame('bar1', $packages['bar1']->extensionName()->name());
        self::assertSame('foo/bar1', $packages['bar1']->name());
        self::assertSame('bar2', $packages['bar2']->extensionName()->name());
        self::assertSame('foo/bar2', $packages['bar2']->name());
    }
}
