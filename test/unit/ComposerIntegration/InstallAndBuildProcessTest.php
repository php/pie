<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Composer\IO\NullIO;
use Composer\Package\CompletePackage;
use Composer\PartialComposer;
use Php\Pie\Building\Build;
use Php\Pie\Building\PlaceholderReplacer;
use Php\Pie\ComposerIntegration\AddInstalledJsonMetadata;
use Php\Pie\ComposerIntegration\InstallAndBuildProcess;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\File\BinaryFile;
use Php\Pie\Installing\Install;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstallAndBuildProcess::class)]
final class InstallAndBuildProcessTest extends TestCase
{
    private Build&MockObject $pieBuild;
    private Install&MockObject $pieInstall;
    private AddInstalledJsonMetadata&MockObject $addInstalledJsonMetadata;

    private InstallAndBuildProcess $installAndBuildProcess;

    public function setUp(): void
    {
        parent::setUp();

        $this->pieBuild                 = $this->createMock(Build::class);
        $this->pieInstall               = $this->createMock(Install::class);
        $this->addInstalledJsonMetadata = $this->createMock(AddInstalledJsonMetadata::class);

        $this->installAndBuildProcess = new InstallAndBuildProcess(
            $this->pieBuild,
            $this->pieInstall,
            $this->addInstalledJsonMetadata,
            $this->createMock(PlaceholderReplacer::class),
        );
    }

    public function testDownloadWithoutBuildAndInstall(): void
    {
        $composer        = $this->createMock(PartialComposer::class);
        $composerRequest = new PieComposerRequest(
            new NullIO(),
            new TargetPlatform(
                OperatingSystem::NonWindows,
                OperatingSystemFamily::Linux,
                PhpBinaryPath::fromCurrentProcess(),
                Architecture::x86_64,
                ThreadSafetyMode::NonThreadSafe,
                1,
                null,
                null,
            ),
            new RequestedPackageAndVersion('foo/bar', '^1.0'),
            PieOperation::Download,
            ['--foo', '--bar="yes"'],
            false,
        );
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $installPath     = '/path/to/install';

        $this->addInstalledJsonMetadata->expects(self::once())->method('addDownloadMetadata');

        $this->addInstalledJsonMetadata->expects(self::never())->method('addBuildMetadata');

        $this->addInstalledJsonMetadata->expects(self::never())->method('addInstallMetadata');

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
        $composer        = $this->createMock(PartialComposer::class);
        $composerRequest = new PieComposerRequest(
            new NullIO(),
            new TargetPlatform(
                OperatingSystem::NonWindows,
                OperatingSystemFamily::Linux,
                PhpBinaryPath::fromCurrentProcess(),
                Architecture::x86_64,
                ThreadSafetyMode::NonThreadSafe,
                1,
                null,
                null,
            ),
            new RequestedPackageAndVersion('foo/bar', '^1.0'),
            PieOperation::Build,
            ['--foo', '--bar="yes"'],
            false,
        );
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $installPath     = '/path/to/install';

        $this->addInstalledJsonMetadata->expects(self::once())->method('addDownloadMetadata');

        $this->addInstalledJsonMetadata->expects(self::once())->method('addBuildMetadata');

        $this->addInstalledJsonMetadata->expects(self::never())->method('addInstallMetadata');

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
        $composer        = $this->createMock(PartialComposer::class);
        $composerRequest = new PieComposerRequest(
            new NullIO(),
            new TargetPlatform(
                OperatingSystem::NonWindows,
                OperatingSystemFamily::Linux,
                PhpBinaryPath::fromCurrentProcess(),
                Architecture::x86_64,
                ThreadSafetyMode::NonThreadSafe,
                1,
                null,
                null,
            ),
            new RequestedPackageAndVersion('foo/bar', '^1.0'),
            PieOperation::Install,
            ['--foo', '--bar="yes"'],
            false,
        );
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $installPath     = '/path/to/install';

        $this->addInstalledJsonMetadata->expects(self::once())->method('addDownloadMetadata');

        $this->addInstalledJsonMetadata->expects(self::once())->method('addBuildMetadata');

        $this->addInstalledJsonMetadata->expects(self::once())->method('addInstallMetadata');

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
