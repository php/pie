<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration\Listeners;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Transaction;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\ComposerIntegration\Listeners\RemoveUnrelatedInstallOperations;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
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
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_map;

#[CoversClass(RemoveUnrelatedInstallOperations::class)]
final class RemoveUnrelatedInstallOperationsTest extends TestCase
{
    private Composer&MockObject $composer;

    public function setUp(): void
    {
        parent::setUp();

        $this->composer = $this->createMock(Composer::class);
    }

    public function testEventListenerRegistration(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('addListener')
            ->with(
                InstallerEvents::PRE_OPERATIONS_EXEC,
                self::isInstanceOf(RemoveUnrelatedInstallOperations::class),
            );

        $this->composer
            ->expects(self::once())
            ->method('getEventDispatcher')
            ->willReturn($eventDispatcher);

        RemoveUnrelatedInstallOperations::selfRegister(
            $this->composer,
            new PieComposerRequest(
                $this->createMock(OutputInterface::class),
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

    /** @psalm-suppress InternalMethod */
    public function testUnrelatedInstallOperationsAreRemoved(): void
    {
        $composerPackage1 = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
        $composerPackage2 = new CompletePackage('bat/baz', '3.4.5.0', '3.4.5');
        $composerPackage3 = new CompletePackage('qux/quux', '5.6.7.0', '5.6.7');

        /**
         * @psalm-suppress InternalClass
         * @psalm-suppress InternalMethod
         */
        $installerEvent = new InstallerEvent(
            InstallerEvents::PRE_OPERATIONS_EXEC,
            $this->composer,
            $this->createMock(IOInterface::class),
            false,
            true,
            new Transaction([], [$composerPackage1, $composerPackage2, $composerPackage3]),
        );

        (new RemoveUnrelatedInstallOperations(
            new PieComposerRequest(
                $this->createMock(OutputInterface::class),
                new TargetPlatform(
                    OperatingSystem::Windows,
                    OperatingSystemFamily::Linux,
                    PhpBinaryPath::fromCurrentProcess(),
                    Architecture::x86_64,
                    ThreadSafetyMode::NonThreadSafe,
                    1,
                    WindowsCompiler::VC15,
                ),
                new RequestedPackageAndVersion('bat/baz', '^3.2'),
                PieOperation::Install,
                [],
                null,
                false,
            ),
        ))($installerEvent);

        self::assertSame(
            ['bat/baz'],
            array_map(
                static fn (InstallOperation $operation): string => $operation->getPackage()->getName(),
                array_filter(
                    $installerEvent->getTransaction()?->getOperations() ?? [],
                    static fn (OperationInterface $operation): bool => $operation instanceof InstallOperation,
                ),
            ),
        );
    }
}
