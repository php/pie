<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration\Listeners;

use Composer\Composer;
use Composer\DependencyResolver\Transaction;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Package\Package;
use Php\Pie\ComposerIntegration\Listeners\OverrideDownloadUrlInstallListener;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Downloading\PackageReleaseAssets;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Platform\WindowsCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(OverrideDownloadUrlInstallListener::class)]
final class OverrideDownloadUrlInstallListenerTest extends TestCase
{
    private Composer&MockObject $composer;
    private IOInterface&MockObject $io;
    private ContainerInterface&MockObject $container;

    public function setUp(): void
    {
        parent::setUp();

        $this->composer  = $this->createMock(Composer::class);
        $this->io        = $this->createMock(IOInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);
    }

    public function testEventListenerRegistration(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('addListener')
            ->with(
                InstallerEvents::PRE_OPERATIONS_EXEC,
                self::isInstanceOf(OverrideDownloadUrlInstallListener::class),
            );

        $this->composer
            ->expects(self::once())
            ->method('getEventDispatcher')
            ->willReturn($eventDispatcher);

        OverrideDownloadUrlInstallListener::selfRegister(
            $this->composer,
            $this->io,
            $this->container,
            new PieComposerRequest(
                $this->createMock(IOInterface::class),
                new TargetPlatform(
                    OperatingSystem::NonWindows,
                    OperatingSystemFamily::Linux,
                    PhpBinaryPath::fromCurrentProcess(),
                    Architecture::x86_64,
                    ThreadSafetyMode::NonThreadSafe,
                    1,
                    WindowsCompiler::VC15,
                ),
                new RequestedPackageAndVersion('foo/bar', '^1.1'),
                PieOperation::Install,
                [],
                null,
                false,
            ),
        );
    }

    public function testNonInstallOperationsAreIgnored(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $composerPackage->setDistUrl('https://example.com/git-archive-zip-url');

        $installerEvent = new InstallerEvent(
            InstallerEvents::PRE_OPERATIONS_EXEC,
            $this->composer,
            $this->io,
            false,
            true,
            new Transaction([$composerPackage], []),
        );

        $this->container
            ->expects(self::never())
            ->method('get');

        (new OverrideDownloadUrlInstallListener(
            $this->composer,
            $this->io,
            $this->container,
            new PieComposerRequest(
                $this->createMock(IOInterface::class),
                new TargetPlatform(
                    OperatingSystem::NonWindows,
                    OperatingSystemFamily::Linux,
                    PhpBinaryPath::fromCurrentProcess(),
                    Architecture::x86_64,
                    ThreadSafetyMode::NonThreadSafe,
                    1,
                    WindowsCompiler::VC15,
                ),
                new RequestedPackageAndVersion('foo/bar', '^1.1'),
                PieOperation::Install,
                [],
                null,
                false,
            ),
        ))($installerEvent);
    }

    public function testNonCompletePackagesAreIgnored(): void
    {
        $composerPackage = new Package('foo/bar', '1.2.3.0', '1.2.3');
        $composerPackage->setDistUrl('https://example.com/git-archive-zip-url');

        $installerEvent = new InstallerEvent(
            InstallerEvents::PRE_OPERATIONS_EXEC,
            $this->composer,
            $this->io,
            false,
            true,
            new Transaction([], [$composerPackage]),
        );

        $this->container
            ->expects(self::never())
            ->method('get');

        (new OverrideDownloadUrlInstallListener(
            $this->composer,
            $this->io,
            $this->container,
            new PieComposerRequest(
                $this->createMock(IOInterface::class),
                new TargetPlatform(
                    OperatingSystem::NonWindows,
                    OperatingSystemFamily::Linux,
                    PhpBinaryPath::fromCurrentProcess(),
                    Architecture::x86_64,
                    ThreadSafetyMode::NonThreadSafe,
                    1,
                    WindowsCompiler::VC15,
                ),
                new RequestedPackageAndVersion('foo/bar', '^1.1'),
                PieOperation::Install,
                [],
                null,
                false,
            ),
        ))($installerEvent);
    }

    public function testInstallOperationsForDifferentPackagesAreIgnored(): void
    {
        $composerPackage = new CompletePackage('different/package', '1.2.3.0', '1.2.3');
        $composerPackage->setDistUrl('https://example.com/git-archive-zip-url');

        $installerEvent = new InstallerEvent(
            InstallerEvents::PRE_OPERATIONS_EXEC,
            $this->composer,
            $this->io,
            false,
            true,
            new Transaction([], [$composerPackage]),
        );

        $this->container
            ->expects(self::never())
            ->method('get');

        (new OverrideDownloadUrlInstallListener(
            $this->composer,
            $this->io,
            $this->container,
            new PieComposerRequest(
                $this->createMock(IOInterface::class),
                new TargetPlatform(
                    OperatingSystem::NonWindows,
                    OperatingSystemFamily::Linux,
                    PhpBinaryPath::fromCurrentProcess(),
                    Architecture::x86_64,
                    ThreadSafetyMode::NonThreadSafe,
                    1,
                    WindowsCompiler::VC15,
                ),
                new RequestedPackageAndVersion('foo/bar', '^1.1'),
                PieOperation::Install,
                [],
                null,
                false,
            ),
        ))($installerEvent);
    }

    public function testWindowsUrlInstallerDoesNotRunOnNonWindows(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $composerPackage->setDistUrl('https://example.com/git-archive-zip-url');

        $installerEvent = new InstallerEvent(
            InstallerEvents::PRE_OPERATIONS_EXEC,
            $this->composer,
            $this->io,
            false,
            true,
            new Transaction([], [$composerPackage]),
        );

        $this->container
            ->expects(self::never())
            ->method('get');

        (new OverrideDownloadUrlInstallListener(
            $this->composer,
            $this->io,
            $this->container,
            new PieComposerRequest(
                $this->createMock(IOInterface::class),
                new TargetPlatform(
                    OperatingSystem::NonWindows,
                    OperatingSystemFamily::Linux,
                    PhpBinaryPath::fromCurrentProcess(),
                    Architecture::x86_64,
                    ThreadSafetyMode::NonThreadSafe,
                    1,
                    WindowsCompiler::VC15,
                ),
                new RequestedPackageAndVersion('foo/bar', '^1.1'),
                PieOperation::Install,
                [],
                null,
                false,
            ),
        ))($installerEvent);

        self::assertSame(
            'https://example.com/git-archive-zip-url',
            $composerPackage->getDistUrl(),
        );
    }

    public function testDistUrlIsUpdatedForWindowsInstallers(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $composerPackage->setDistUrl('https://example.com/git-archive-zip-url');

        $installerEvent = new InstallerEvent(
            InstallerEvents::PRE_OPERATIONS_EXEC,
            $this->composer,
            $this->io,
            false,
            true,
            new Transaction([], [$composerPackage]),
        );

        $packageReleaseAssets = $this->createMock(PackageReleaseAssets::class);
        $packageReleaseAssets
            ->expects(self::once())
            ->method('findMatchingReleaseAssetUrl')
            ->willReturn('https://example.com/windows-download-url');

        $this->container
            ->method('get')
            ->with(PackageReleaseAssets::class)
            ->willReturn($packageReleaseAssets);

        (new OverrideDownloadUrlInstallListener(
            $this->composer,
            $this->io,
            $this->container,
            new PieComposerRequest(
                $this->createMock(IOInterface::class),
                new TargetPlatform(
                    OperatingSystem::Windows,
                    OperatingSystemFamily::Linux,
                    PhpBinaryPath::fromCurrentProcess(),
                    Architecture::x86_64,
                    ThreadSafetyMode::NonThreadSafe,
                    1,
                    WindowsCompiler::VC15,
                ),
                new RequestedPackageAndVersion('foo/bar', '^1.1'),
                PieOperation::Install,
                [],
                null,
                false,
            ),
        ))($installerEvent);

        self::assertSame(
            'https://example.com/windows-download-url',
            $composerPackage->getDistUrl(),
        );
    }

    public function testDistUrlIsUpdatedForPrePackagedTgzSource(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $composerPackage->setDistType('zip');
        $composerPackage->setDistUrl('https://example.com/git-archive-zip-url');
        $composerPackage->setPhpExt([
            'extension-name' => 'foobar',
            'download-url-method' => 'pre-packaged-source',
        ]);

        $installerEvent = new InstallerEvent(
            InstallerEvents::PRE_OPERATIONS_EXEC,
            $this->composer,
            $this->io,
            false,
            true,
            new Transaction([], [$composerPackage]),
        );

        $packageReleaseAssets = $this->createMock(PackageReleaseAssets::class);
        $packageReleaseAssets
            ->expects(self::once())
            ->method('findMatchingReleaseAssetUrl')
            ->willReturn('https://example.com/pre-packaged-source-download-url.tgz');

        $this->container
            ->method('get')
            ->with(PackageReleaseAssets::class)
            ->willReturn($packageReleaseAssets);

        (new OverrideDownloadUrlInstallListener(
            $this->composer,
            $this->io,
            $this->container,
            new PieComposerRequest(
                $this->createMock(IOInterface::class),
                new TargetPlatform(
                    OperatingSystem::NonWindows,
                    OperatingSystemFamily::Linux,
                    PhpBinaryPath::fromCurrentProcess(),
                    Architecture::x86_64,
                    ThreadSafetyMode::NonThreadSafe,
                    1,
                    WindowsCompiler::VC15,
                ),
                new RequestedPackageAndVersion('foo/bar', '^1.1'),
                PieOperation::Install,
                [],
                null,
                false,
            ),
        ))($installerEvent);

        self::assertSame(
            'https://example.com/pre-packaged-source-download-url.tgz',
            $composerPackage->getDistUrl(),
        );
        self::assertSame('tar', $composerPackage->getDistType());
    }
}
