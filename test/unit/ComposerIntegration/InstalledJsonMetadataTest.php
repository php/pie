<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\RepositoryManager;
use Php\Pie\BinaryFile;
use Php\Pie\ComposerIntegration\InstalledJsonMetadata;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(InstalledJsonMetadata::class)]
final class InstalledJsonMetadataTest extends TestCase
{
    private function mockComposerInstalledRepositoryWith(CompletePackage $package): Composer&MockObject
    {
        $installedRepository = new InstalledArrayRepository([$package]);

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager->method('getLocalRepository')->willReturn($installedRepository);

        $composer = $this->createMock(Composer::class);
        $composer->method('getRepositoryManager')->willReturn($repositoryManager);

        return $composer;
    }

    public function testMetadataForDownloads(): void
    {
        $package = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');

        $phpBinary = PhpBinaryPath::fromCurrentProcess();

        (new InstalledJsonMetadata())->addDownloadMetadata(
            $this->mockComposerInstalledRepositoryWith($package),
            new PieComposerRequest(
                $this->createMock(OutputInterface::class),
                new TargetPlatform(
                    OperatingSystem::NonWindows,
                    $phpBinary,
                    Architecture::x86_64,
                    ThreadSafetyMode::NonThreadSafe,
                    1,
                    null,
                ),
                new RequestedPackageAndVersion('foo/bar', '^1.0'),
                PieOperation::Build,
                ['--foo', '--bar="yes"'],
            ),
            clone $package,
        );

        self::assertSame(
            [
                'pie-target-platform-php-path' => $phpBinary->phpBinaryPath,
                'pie-target-platform-php-config-path' => $phpBinary->phpConfigPath(),
                'pie-target-platform-php-version' => $phpBinary->version(),
                'pie-target-platform-php-thread-safety' => 'NonThreadSafe',
                'pie-target-platform-php-windows-compiler' => null,
                'pie-target-platform-architecture' => 'x86_64',
            ],
            $package->getExtra(),
        );
    }

    public function testMetadataForBuilds(): void
    {
        $package = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');

        (new InstalledJsonMetadata())->addBuildMetadata(
            $this->mockComposerInstalledRepositoryWith($package),
            new PieComposerRequest(
                $this->createMock(OutputInterface::class),
                new TargetPlatform(
                    OperatingSystem::NonWindows,
                    PhpBinaryPath::fromCurrentProcess(),
                    Architecture::x86_64,
                    ThreadSafetyMode::NonThreadSafe,
                    1,
                    null,
                ),
                new RequestedPackageAndVersion('foo/bar', '^1.0'),
                PieOperation::Build,
                ['--foo', '--bar="yes"'],
            ),
            clone $package,
            new BinaryFile('/path/to/built', 'sha256-checksum-value'),
        );

        self::assertSame(
            [
                'pie-configure-options' => '--foo --bar="yes"',
                'pie-built-binary' => '/path/to/built',
                'pie-installed-binary-checksum' => 'sha256-checksum-value',
            ],
            $package->getExtra(),
        );
    }

    public function testMetadataForInstalls(): void
    {
        $package = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');

        (new InstalledJsonMetadata())->addInstallMetadata(
            $this->mockComposerInstalledRepositoryWith($package),
            clone $package,
            new BinaryFile('/path/to/installed', 'ignore'),
        );

        self::assertSame(
            ['pie-installed-binary' => '/path/to/installed'],
            $package->getExtra(),
        );
    }
}
