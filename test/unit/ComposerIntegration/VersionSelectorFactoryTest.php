<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Repository\ArrayRepository;
use Composer\Repository\RepositoryManager;
use Php\Pie\ComposerIntegration\VersionSelectorFactory;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VersionSelectorFactory::class)]
final class VersionSelectorFactoryTest extends TestCase
{
    public function testVersionSelectorFactory(): void
    {
        $repository = new ArrayRepository([
            new CompletePackage('another/package', '1.5.0.0', '1.5.0'),
            new CompletePackage('foo/bar', '1.2.3.0', '1.2.3'),
            new CompletePackage('foo/bar', '2.0.0.0', '2.0.0'),
        ]);

        $repoMananger = $this->createMock(RepositoryManager::class);
        $repoMananger
            ->expects(self::once())
            ->method('getRepositories')
            ->willReturn([$repository]);

        $composer = $this->createMock(Composer::class);
        $composer
            ->expects(self::once())
            ->method('getRepositoryManager')
            ->willReturn($repoMananger);

        $versionSelector = VersionSelectorFactory::make(
            $composer,
            new RequestedPackageAndVersion('foo/bar', '^1.0'),
            new TargetPlatform(
                OperatingSystem::NonWindows,
                OperatingSystemFamily::Linux,
                PhpBinaryPath::fromCurrentProcess(),
                Architecture::x86_64,
                ThreadSafetyMode::NonThreadSafe,
                1,
                null,
            ),
        );

        $package = $versionSelector->findBestCandidate('foo/bar', '^1.0');
        self::assertNotFalse($package);
        self::assertSame('foo/bar', $package->getName());
        self::assertSame('1.2.3', $package->getPrettyVersion());
    }
}
