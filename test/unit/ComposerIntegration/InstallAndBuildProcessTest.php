<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Composer\Package\CompletePackage;
use Composer\PartialComposer;
use Php\Pie\BinaryFile;
use Php\Pie\Building\Build;
use Php\Pie\ComposerIntegration\InstallAndBuildProcess;
use Php\Pie\ComposerIntegration\InstalledJsonMetadata;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Installing\Install;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(InstallAndBuildProcess::class)]
final class InstallAndBuildProcessTest extends TestCase
{
    private Build&MockObject $pieBuild;
    private Install&MockObject $pieInstall;
    private InstalledJsonMetadata&MockObject $installedJsonMetadata;

    private InstallAndBuildProcess $installAndBuildProcess;

    public function setUp(): void
    {
        parent::setUp();

        $this->pieBuild              = $this->createMock(Build::class);
        $this->pieInstall            = $this->createMock(Install::class);
        $this->installedJsonMetadata = $this->createMock(InstalledJsonMetadata::class);

        $this->installAndBuildProcess = new InstallAndBuildProcess(
            $this->pieBuild,
            $this->pieInstall,
            $this->installedJsonMetadata,
        );
    }

    public function testDownloadWithoutBuildAndInstall(): void
    {
        $symfonyOutput   = $this->createMock(OutputInterface::class);
        $composer        = $this->createMock(PartialComposer::class);
        $composerRequest = new PieComposerRequest(
            $symfonyOutput,
            new TargetPlatform(
                OperatingSystem::NonWindows,
                PhpBinaryPath::fromCurrentProcess(),
                Architecture::x86_64,
                ThreadSafetyMode::NonThreadSafe,
                1,
                null,
            ),
            new RequestedPackageAndVersion('foo/bar', '^1.0'),
            PieOperation::Download,
            ['--foo', '--bar="yes"'],
        );
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $installPath     = '/path/to/install';

        $this->installedJsonMetadata->expects(self::once())->method('addDownloadMetadata');

        $this->installedJsonMetadata->expects(self::never())->method('addBuildMetadata');

        $this->installedJsonMetadata->expects(self::never())->method('addInstallMetadata');

        $this->pieBuild->expects(self::never())->method('__invoke');

        $this->pieInstall->expects(self::never())->method('__invoke');

        ($this->installAndBuildProcess)(
            $composer,
            $composerRequest,
            $composerPackage,
            $installPath,
        );
    }

    public function testDownloadAndBuildWithoutInstall(): void
    {
        $symfonyOutput   = $this->createMock(OutputInterface::class);
        $composer        = $this->createMock(PartialComposer::class);
        $composerRequest = new PieComposerRequest(
            $symfonyOutput,
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
        );
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $installPath     = '/path/to/install';

        $this->installedJsonMetadata->expects(self::once())->method('addDownloadMetadata');

        $this->installedJsonMetadata->expects(self::once())->method('addBuildMetadata');

        $this->installedJsonMetadata->expects(self::never())->method('addInstallMetadata');

        $this->pieBuild
            ->expects(self::once())
            ->method('__invoke')
            ->willReturn(new BinaryFile('/path/to/built/file', 'checksumvalue'));

        $this->pieInstall->expects(self::never())->method('__invoke');

        ($this->installAndBuildProcess)(
            $composer,
            $composerRequest,
            $composerPackage,
            $installPath,
        );
    }

    public function testDownloadBuildAndInstall(): void
    {
        $symfonyOutput   = $this->createMock(OutputInterface::class);
        $composer        = $this->createMock(PartialComposer::class);
        $composerRequest = new PieComposerRequest(
            $symfonyOutput,
            new TargetPlatform(
                OperatingSystem::NonWindows,
                PhpBinaryPath::fromCurrentProcess(),
                Architecture::x86_64,
                ThreadSafetyMode::NonThreadSafe,
                1,
                null,
            ),
            new RequestedPackageAndVersion('foo/bar', '^1.0'),
            PieOperation::Install,
            ['--foo', '--bar="yes"'],
        );
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $installPath     = '/path/to/install';

        $this->installedJsonMetadata->expects(self::once())->method('addDownloadMetadata');

        $this->installedJsonMetadata->expects(self::once())->method('addBuildMetadata');

        $this->installedJsonMetadata->expects(self::once())->method('addInstallMetadata');

        $this->pieBuild
            ->expects(self::once())
            ->method('__invoke')
            ->willReturn(new BinaryFile('/path/to/built/file', 'checksumvalue'));

        $this->pieInstall
            ->expects(self::once())
            ->method('__invoke')
            ->willReturn(new BinaryFile('/path/to/installed/file', 'checksumvalue'));

        ($this->installAndBuildProcess)(
            $composer,
            $composerRequest,
            $composerPackage,
            $installPath,
        );
    }
}
