<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Composer\Composer;
use Composer\DependencyResolver\Transaction;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Php\Pie\ComposerIntegration\OverrideWindowsUrlInstallListener;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Downloading\PackageReleaseAssets;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Platform\WindowsCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(OverrideWindowsUrlInstallListener::class)]
final class OverrideWindowsUrlInstallListenerTest extends TestCase
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
                self::isInstanceOf(OverrideWindowsUrlInstallListener::class),
            );

        $this->composer
            ->expects(self::once())
            ->method('getEventDispatcher')
            ->willReturn($eventDispatcher);

        OverrideWindowsUrlInstallListener::selfRegister(
            $this->composer,
            $this->io,
            $this->container,
            new PieComposerRequest(
                $this->createMock(OutputInterface::class),
                new TargetPlatform(
                    OperatingSystem::NonWindows,
                    PhpBinaryPath::fromCurrentProcess(),
                    Architecture::x86_64,
                    ThreadSafetyMode::NonThreadSafe,
                    1,
                    WindowsCompiler::VC15,
                ),
                new RequestedPackageAndVersion('foo/bar', '^1.1'),
                PieOperation::Install,
                [],
            ),
        );
    }

    public function testWindowsUrlInstallerDoesNotRunOnNonWindows(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $composerPackage->setDistUrl('https://example.com/git-archive-zip-url');

        /**
         * @psalm-suppress InternalClass
         * @psalm-suppress InternalMethod
         */
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

        (new OverrideWindowsUrlInstallListener(
            $this->composer,
            $this->io,
            $this->container,
            new PieComposerRequest(
                $this->createMock(OutputInterface::class),
                new TargetPlatform(
                    OperatingSystem::NonWindows,
                    PhpBinaryPath::fromCurrentProcess(),
                    Architecture::x86_64,
                    ThreadSafetyMode::NonThreadSafe,
                    1,
                    WindowsCompiler::VC15,
                ),
                new RequestedPackageAndVersion('foo/bar', '^1.1'),
                PieOperation::Install,
                [],
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

        /**
         * @psalm-suppress InternalClass
         * @psalm-suppress InternalMethod
         */
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
            ->method('findWindowsDownloadUrlForPackage')
            ->willReturn('https://example.com/windows-download-url');

        $this->container
            ->method('get')
            ->with(PackageReleaseAssets::class)
            ->willReturn($packageReleaseAssets);

        (new OverrideWindowsUrlInstallListener(
            $this->composer,
            $this->io,
            $this->container,
            new PieComposerRequest(
                $this->createMock(OutputInterface::class),
                new TargetPlatform(
                    OperatingSystem::Windows,
                    PhpBinaryPath::fromCurrentProcess(),
                    Architecture::x86_64,
                    ThreadSafetyMode::NonThreadSafe,
                    1,
                    WindowsCompiler::VC15,
                ),
                new RequestedPackageAndVersion('foo/bar', '^1.1'),
                PieOperation::Install,
                [],
            ),
        ))($installerEvent);

        self::assertSame(
            'https://example.com/windows-download-url',
            $composerPackage->getDistUrl(),
        );
    }
}
