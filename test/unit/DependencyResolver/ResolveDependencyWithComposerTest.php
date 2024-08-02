<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Repository\CompositeRepository;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\RepositoryManager;
use Php\Pie\DependencyResolver\ResolveDependencyWithComposer;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPhp\ResolveTargetPhpToPlatformRepository;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResolveDependencyWithComposer::class)]
final class ResolveDependencyWithComposerTest extends TestCase
{
    private Composer $composer;
    private ResolveTargetPhpToPlatformRepository $resolveTargetPhpToPlatformRepository;

    public function setUp(): void
    {
        parent::setUp();

        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getRepositories')
            ->willReturn([new CompositeRepository(RepositoryFactory::defaultReposWithDefaultManager(new NullIO()))]);

        $this->composer = $this->createMock(Composer::class);
        $this->composer->method('getRepositoryManager')
            ->willReturn($repoManager);

        $this->resolveTargetPhpToPlatformRepository = new ResolveTargetPhpToPlatformRepository();
    }

    public function testPackageThatCanBeResolved(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('version')
            ->willReturn('8.3.0');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            null,
        );

        $package = (new ResolveDependencyWithComposer(
            $this->composer,
            $this->resolveTargetPhpToPlatformRepository,
        ))($targetPlatform, 'asgrim/example-pie-extension', '^1.0');

        self::assertSame('asgrim/example-pie-extension', $package->name);
        self::assertStringStartsWith('1.', $package->version);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string, 2: string}>
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public static function unresolvableDependencies(): array
    {
        return [
            'phpVersionTooOld' => [['php' => '8.1.0'], 'asgrim/example-pie-extension', '1.0.0'],
            'phpVersionTooNew' => [['php' => '8.4.0'], 'asgrim/example-pie-extension', '1.0.0'],
            'notAPhpExtension' => [['php' => '8.3.0'], 'ramsey/uuid', '^4.7'],
        ];
    }

    /** @param array<string, string> $platformOverrides */
    #[DataProvider('unresolvableDependencies')]
    public function testPackageThatCannotBeResolvedThrowsException(array $platformOverrides, string $package, string $version): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::once())
            ->method('version')
            ->willReturn($platformOverrides['php']);

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            null,
        );

        $this->expectException(UnableToResolveRequirement::class);

        (new ResolveDependencyWithComposer(
            $this->composer,
            $this->resolveTargetPhpToPlatformRepository,
        ))(
            $targetPlatform,
            $package,
            $version,
        );
    }
}
