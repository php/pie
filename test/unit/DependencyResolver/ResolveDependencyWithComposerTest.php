<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Repository\ArrayRepository;
use Composer\Repository\CompositeRepository;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\RepositoryManager;
use Composer\Semver\Constraint\Constraint;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\DependencyResolver\IncompatibleOperatingSystemFamily;
use Php\Pie\DependencyResolver\IncompatibleThreadSafetyMode;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\DependencyResolver\ResolveDependencyWithComposer;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(ResolveDependencyWithComposer::class)]
final class ResolveDependencyWithComposerTest extends TestCase
{
    private Composer $composer;

    private InstalledRepositoryInterface&MockObject $localRepo;

    public function setUp(): void
    {
        parent::setUp();

        $packageWithReplaces = new CompletePackage('already/installed2', '1.2.3.0', '1.2.3');
        $packageWithReplaces->setReplaces([
            'ext-installed2' => new Link('root/package', 'ext-installed2', new Constraint('==', '*')),
        ]);
        $this->localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $this->localRepo->method('getPackages')->willReturn([
            new CompletePackage('already/installed1', '1.2.3.0', '1.2.3'),
            $packageWithReplaces,
        ]);

        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getRepositories')
            ->willReturn([new CompositeRepository(RepositoryFactory::defaultReposWithDefaultManager(new NullIO()))]);
        $repoManager->method('getLocalRepository')->willReturn($this->localRepo);

        $this->composer = $this->createMock(Composer::class);
        $this->composer->method('getRepositoryManager')
            ->willReturn($repoManager);
    }

    public function testPackageThatCanBeResolved(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('version')
            ->willReturn('8.3.0');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $package = (new ResolveDependencyWithComposer(
            $this->createMock(OutputInterface::class),
            $this->createMock(QuieterConsoleIO::class),
        ))($this->composer, $targetPlatform, new RequestedPackageAndVersion('asgrim/example-pie-extension', '^1.0'), false);

        self::assertSame('asgrim/example-pie-extension', $package->name());
        self::assertStringStartsWith('1.', $package->version());
    }

    /** @return array<string, array{0: array<string, string>, 1: non-empty-string, 2: non-empty-string}> */
    public static function unresolvableDependencies(): array
    {
        return [
            'phpVersionTooOld' => [['php' => '8.1.0'], 'asgrim/example-pie-extension', '1.0.0'],
            'phpVersionTooNew' => [['php' => '8.4.0'], 'asgrim/example-pie-extension', '1.0.0'],
            'notAPhpExtension' => [['php' => '8.3.0'], 'ramsey/uuid', '^4.7'],
        ];
    }

    /**
     * @param array<string, string> $platformOverrides
     * @param non-empty-string      $package
     * @param non-empty-string      $version
     */
    #[DataProvider('unresolvableDependencies')]
    public function testPackageThatCannotBeResolvedThrowsException(array $platformOverrides, string $package, string $version): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::once())
            ->method('version')
            ->willReturn($platformOverrides['php']);

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $this->expectException(UnableToResolveRequirement::class);

        (new ResolveDependencyWithComposer(
            $this->createMock(OutputInterface::class),
            $this->createMock(QuieterConsoleIO::class),
        ))(
            $this->composer,
            $targetPlatform,
            new RequestedPackageAndVersion(
                $package,
                $version,
            ),
            false,
        );
    }

    /**
     * @param array<string, string> $platformOverrides
     * @param non-empty-string      $package
     * @param non-empty-string      $version
     */
    #[DataProvider('unresolvableDependencies')]
    public function testUnresolvedPackageCanBeInstalledWithForceOption(array $platformOverrides, string $package, string $version): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::once())
            ->method('version')
            ->willReturn($platformOverrides['php']);

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $this->expectException(UnableToResolveRequirement::class);

        $package = (new ResolveDependencyWithComposer(
            $this->createMock(OutputInterface::class),
            $this->createMock(QuieterConsoleIO::class),
        ))(
            $this->composer,
            $targetPlatform,
            new RequestedPackageAndVersion(
                $package,
                $version,
            ),
            true,
        );

        self::assertSame('asgrim/example-pie-extension', $package->name());
        self::assertStringStartsWith('1.', $package->version());
    }

    public function testZtsOnlyPackageCannotBeInstalledOnNtsSystem(): void
    {
        $pkg = new CompletePackage('test-vendor/test-package', '1.0.0.0', '1.0.0');
        $pkg->setType('php-ext');
        $pkg->setPhpExt([
            'extension-name' => 'testext',
            'support-nts' => false,
        ]);

        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getRepositories')
            ->willReturn([new ArrayRepository([$pkg])]);
        $repoManager->method('getLocalRepository')->willReturn($this->localRepo);

        $this->composer = $this->createMock(Composer::class);
        $this->composer->method('getRepositoryManager')
            ->willReturn($repoManager);

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('version')
            ->willReturn('8.3.0');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
        );

        $this->expectException(IncompatibleThreadSafetyMode::class);
        $this->expectExceptionMessage('This extension does not support being installed on a non-Thread Safe PHP installation');
        (new ResolveDependencyWithComposer(
            $this->createMock(OutputInterface::class),
            $this->createMock(QuieterConsoleIO::class),
        ))(
            $this->composer,
            $targetPlatform,
            new RequestedPackageAndVersion(
                'test-vendor/test-package',
                '1.0.0',
            ),
            false,
        );
    }

    public function testNtsOnlyPackageCannotBeInstalledOnZtsSystem(): void
    {
        $pkg = new CompletePackage('test-vendor/test-package', '1.0.0.0', '1.0.0');
        $pkg->setType('php-ext');
        $pkg->setPhpExt([
            'extension-name' => 'testext',
            'support-zts' => false,
        ]);

        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getRepositories')
            ->willReturn([new ArrayRepository([$pkg])]);
        $repoManager->method('getLocalRepository')->willReturn($this->localRepo);

        $this->composer = $this->createMock(Composer::class);
        $this->composer->method('getRepositoryManager')
            ->willReturn($repoManager);

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('version')
            ->willReturn('8.3.0');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $this->expectException(IncompatibleThreadSafetyMode::class);
        $this->expectExceptionMessage('This extension does not support being installed on a Thread Safe PHP installation');
        (new ResolveDependencyWithComposer(
            $this->createMock(OutputInterface::class),
            $this->createMock(QuieterConsoleIO::class),
        ))(
            $this->composer,
            $targetPlatform,
            new RequestedPackageAndVersion(
                'test-vendor/test-package',
                '1.0.0',
            ),
            false,
        );
    }

    public function testExtensionCanOnlyBeInstalledIfOsFamilyIsCompatible(): void
    {
        $pkg = new CompletePackage('test-vendor/test-package', '1.0.0.0', '1.0.0');
        $pkg->setType('php-ext');
        $pkg->setPhpExt([
            'extension-name' => 'testext',
            'os-families' => ['Solaris', 'Darwin'],
        ]);

        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getRepositories')
            ->willReturn([new ArrayRepository([$pkg])]);
        $repoManager->method('getLocalRepository')->willReturn($this->localRepo);

        $this->composer = $this->createMock(Composer::class);
        $this->composer->method('getRepositoryManager')
            ->willReturn($repoManager);

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('version')
            ->willReturn('8.3.0');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $this->expectException(IncompatibleOperatingSystemFamily::class);
        $this->expectExceptionMessage('This extension does not support the "linux" operating system family. It is compatible with the following families: "solaris", "darwin"');
        (new ResolveDependencyWithComposer(
            $this->createMock(OutputInterface::class),
            $this->createMock(QuieterConsoleIO::class),
        ))(
            $this->composer,
            $targetPlatform,
            new RequestedPackageAndVersion(
                'test-vendor/test-package',
                '1.0.0',
            ),
            false,
        );
    }

    public function testExtensionCanOnlyBeInstalledIfOsFamilyIsNotInCompatible(): void
    {
        $pkg = new CompletePackage('test-vendor/test-package', '1.0.0.0', '1.0.0');
        $pkg->setType('php-ext');
        $pkg->setPhpExt([
            'extension-name' => 'testext',
            'os-families-exclude' => ['Darwin', 'Solaris'],
        ]);

        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getRepositories')
            ->willReturn([new ArrayRepository([$pkg])]);
        $repoManager->method('getLocalRepository')->willReturn($this->localRepo);

        $this->composer = $this->createMock(Composer::class);
        $this->composer->method('getRepositoryManager')
            ->willReturn($repoManager);

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('version')
            ->willReturn('8.3.0');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Darwin,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $this->expectException(IncompatibleOperatingSystemFamily::class);
        $this->expectExceptionMessage('This extension does not support the "darwin" operating system family. It is incompatible with the following families: "darwin", "solaris".');
        (new ResolveDependencyWithComposer(
            $this->createMock(OutputInterface::class),
            $this->createMock(QuieterConsoleIO::class),
        ))(
            $this->composer,
            $targetPlatform,
            new RequestedPackageAndVersion(
                'test-vendor/test-package',
                '1.0.0',
            ),
            false,
        );
    }

    public function testPackageThatCanBeResolvedWithReplaceConflict(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('version')
            ->willReturn('8.3.0');
        $phpBinaryPath->expects(self::any())
            ->method('extensions')
            ->willReturn([
                'installed1' => '1.2.3',
                'installed2' => '1.2.3',
            ]);

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        $package = (new ResolveDependencyWithComposer(
            $this->createMock(OutputInterface::class),
            $this->createMock(QuieterConsoleIO::class),
        ))($this->composer, $targetPlatform, new RequestedPackageAndVersion('asgrim/example-pie-extension', '^1.0'), false);

        self::assertSame('asgrim/example-pie-extension', $package->name());
        self::assertStringStartsWith('1.', $package->version());
    }
}
