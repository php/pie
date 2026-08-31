<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration\Listeners;

use Composer\Composer;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Transaction;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Package\Package;
use Php\Pie\ComposerIntegration\Listeners\AllDownloadUrlMethodsSuppressed;
use Php\Pie\ComposerIntegration\Listeners\CouldNotDetermineDownloadUrlMethod;
use Php\Pie\ComposerIntegration\Listeners\OverrideDownloadUrlInstallListener;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Downloading\DownloadUrlMethod;
use Php\Pie\Downloading\Exception\CouldNotFindReleaseAsset;
use Php\Pie\Downloading\MatchedReleaseAsset;
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

use function array_map;

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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                [],
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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                [],
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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                [],
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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                [],
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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                [],
                false,
            ),
        ))($installerEvent);

        self::assertSame(
            'https://example.com/git-archive-zip-url',
            $composerPackage->getDistUrl(),
        );
        self::assertSame(DownloadUrlMethod::ComposerDefaultDownload, DownloadUrlMethod::fromComposerPackage($composerPackage));
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
            ->method('findMatchingReleaseAsset')
            ->willReturn(new MatchedReleaseAsset(
                'https://api.github.com/repos/foo/bar/releases/assets/12345',
                'php_foo-1.2.3-8.3-vc14-ts-x86.zip',
            ));

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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                [],
                false,
            ),
        ))($installerEvent);

        self::assertSame(
            'https://example.com/windows-download-url',
            $composerPackage->getDistUrl(),
        );
        self::assertSame(DownloadUrlMethod::WindowsBinaryDownload, DownloadUrlMethod::fromComposerPackage($composerPackage));
        self::assertSame(['http' => ['header' => ['Accept: application/octet-stream']]], $composerPackage->getTransportOptions());
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
            ->method('findMatchingReleaseAsset')
            ->willReturn(new MatchedReleaseAsset(
                'https://api.github.com/repos/foo/bar/releases/assets/12345',
                'php_foobar-1.2.3-src.tgz',
            ));

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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                [],
                false,
            ),
        ))($installerEvent);

        self::assertSame(
            'https://example.com/pre-packaged-source-download-url.tgz',
            $composerPackage->getDistUrl(),
        );
        self::assertSame(DownloadUrlMethod::PrePackagedSourceDownload, DownloadUrlMethod::fromComposerPackage($composerPackage));
        self::assertSame('tar', $composerPackage->getDistType());
    }

    public function testDistUrlIsUpdatedForPrePackagedTgzBinaryWhenBinaryIsFound(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $composerPackage->setDistType('zip');
        $composerPackage->setDistUrl('https://example.com/git-archive-zip-url');
        $composerPackage->setPhpExt([
            'extension-name' => 'foobar',
            'download-url-method' => ['pre-packaged-binary', 'composer-default'],
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
            ->method('findMatchingReleaseAsset')
            ->willReturn(new MatchedReleaseAsset(
                'https://api.github.com/repos/foo/bar/releases/assets/12345',
                'php_foobar-1.2.3_php8.3-x86_64-linux-glibc-zts.tgz',
            ));

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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                [],
                false,
            ),
        ))($installerEvent);

        self::assertSame(
            'https://api.github.com/repos/foo/bar/releases/assets/12345',
            $composerPackage->getDistUrl(),
        );
        self::assertSame(DownloadUrlMethod::PrePackagedBinary, DownloadUrlMethod::fromComposerPackage($composerPackage));
        self::assertSame('tar', $composerPackage->getDistType());
        self::assertSame(['http' => ['header' => ['Accept: application/octet-stream']]], $composerPackage->getTransportOptions());
    }

    public function testDistUrlIsUpdatedForPrePackagedTgzBinaryWhenBinaryIsNotFound(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $composerPackage->setDistType('zip');
        $composerPackage->setDistUrl('https://example.com/git-archive-zip-url');
        $composerPackage->setPhpExt([
            'extension-name' => 'foobar',
            'download-url-method' => ['pre-packaged-binary', 'composer-default'],
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
            ->method('findMatchingReleaseAsset')
            ->willThrowException(new CouldNotFindReleaseAsset('nope not found'));

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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                [],
                false,
            ),
        ))($installerEvent);

        self::assertSame(
            'https://example.com/git-archive-zip-url',
            $composerPackage->getDistUrl(),
        );
        self::assertSame(DownloadUrlMethod::ComposerDefaultDownload, DownloadUrlMethod::fromComposerPackage($composerPackage));
        self::assertSame('zip', $composerPackage->getDistType());
    }

    public function testPrePackagedBinaryMethodIsIgnoredWhenConfigureOptionsArePassed(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $composerPackage->setDistType('zip');
        $composerPackage->setDistUrl('https://example.com/git-archive-zip-url');
        $composerPackage->setPhpExt([
            'extension-name' => 'foobar',
            'download-url-method' => ['pre-packaged-binary'],
        ]);

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

        $listener = new OverrideDownloadUrlInstallListener(
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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                ['foo/bar' => ['--with-foo']],
                false,
            ),
        );

        $this->expectException(CouldNotDetermineDownloadUrlMethod::class);
        $this->expectExceptionMessage('Could not download foo/bar using pre-packaged-binary method: Cannot use pre-packaged-binary download method, as configure options were passed.');
        $listener($installerEvent);
    }

    public function testDistUrlIsUpdatedForWindowsInstallersOnUpdateOperations(): void
    {
        $initialPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $initialPackage->setDistUrl('https://example.com/git-archive-zip-url');

        $targetPackage = new CompletePackage('foo/bar', '1.3.0.0', '1.3.0');
        $targetPackage->setDistUrl('https://example.com/git-archive-zip-url');

        $installerEvent = new InstallerEvent(
            InstallerEvents::PRE_OPERATIONS_EXEC,
            $this->composer,
            $this->io,
            false,
            true,
            new Transaction([$initialPackage], [$targetPackage]),
        );

        self::assertSame(
            [UpdateOperation::class],
            array_map(
                static fn (object $operation): string => $operation::class,
                $installerEvent->getTransaction()?->getOperations() ?? [],
            ),
        );

        $packageReleaseAssets = $this->createMock(PackageReleaseAssets::class);
        $packageReleaseAssets
            ->expects(self::once())
            ->method('findMatchingReleaseAsset')
            ->willReturn(new MatchedReleaseAsset(
                'https://api.github.com/repos/foo/bar/releases/assets/12345',
                'php_foo-1.2.3-8.3-vc14-ts-x86.zip',
            ));

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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                [],
                false,
            ),
        ))($installerEvent);

        self::assertSame(
            'https://example.com/windows-download-url',
            $targetPackage->getDistUrl(),
        );
        self::assertSame(DownloadUrlMethod::WindowsBinaryDownload, DownloadUrlMethod::fromComposerPackage($targetPackage));
    }

    public function testNoSelectedDownloadUrlMethodWillThrowException(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $composerPackage->setDistType('zip');
        $composerPackage->setDistUrl('https://example.com/git-archive-zip-url');
        $composerPackage->setPhpExt([
            'extension-name' => 'foobar',
            'download-url-method' => ['pre-packaged-binary'],
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
            ->method('findMatchingReleaseAsset')
            ->willThrowException(new CouldNotFindReleaseAsset('nope not found'));

        $this->container
            ->method('get')
            ->with(PackageReleaseAssets::class)
            ->willReturn($packageReleaseAssets);

        $listener = new OverrideDownloadUrlInstallListener(
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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                [],
                false,
            ),
        );

        $this->expectException(CouldNotDetermineDownloadUrlMethod::class);
        $this->expectExceptionMessage('Could not download foo/bar using pre-packaged-binary method: nope not found');
        $listener($installerEvent);
    }

    public function testSuppressedDownloadUrlMethodIsSkipped(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $composerPackage->setDistType('zip');
        $composerPackage->setDistUrl('https://example.com/git-archive-zip-url');
        $composerPackage->setPhpExt([
            'extension-name' => 'foobar',
            'download-url-method' => ['pre-packaged-binary', 'composer-default'],
        ]);

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

        /** @var list<string|array<string>> $writtenAtVerbose */
        $writtenAtVerbose = [];
        $this->io
            ->method('write')
            ->willReturnCallback(
                static function (string|array $messages, bool $newline = true, int $verbosity = IOInterface::NORMAL) use (&$writtenAtVerbose): void {
                    if ($verbosity !== IOInterface::VERBOSE) {
                        return;
                    }

                    $writtenAtVerbose[] = $messages;
                },
            );

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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                [],
                false,
                suppressedDownloadUrlMethods: [DownloadUrlMethod::PrePackagedBinary],
            ),
        ))($installerEvent);

        self::assertSame(
            'https://example.com/git-archive-zip-url',
            $composerPackage->getDistUrl(),
        );
        self::assertSame(DownloadUrlMethod::ComposerDefaultDownload, DownloadUrlMethod::fromComposerPackage($composerPackage));
        self::assertContains('Suppressing download method: pre-packaged-binary', $writtenAtVerbose);
    }

    public function testSuppressingAllDownloadUrlMethodsWillThrowException(): void
    {
        $composerPackage = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $composerPackage->setDistType('zip');
        $composerPackage->setDistUrl('https://example.com/git-archive-zip-url');
        $composerPackage->setPhpExt([
            'extension-name' => 'foobar',
            'download-url-method' => ['pre-packaged-binary', 'composer-default'],
        ]);

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

        $listener = new OverrideDownloadUrlInstallListener(
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
                    null,
                ),
                [new RequestedPackageAndVersion('foo/bar', '^1.1')],
                PieOperation::Install,
                [],
                false,
                suppressedDownloadUrlMethods: [DownloadUrlMethod::PrePackagedBinary, DownloadUrlMethod::ComposerDefaultDownload],
            ),
        );

        $this->expectException(AllDownloadUrlMethodsSuppressed::class);
        $listener($installerEvent);
    }
}
