<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Composer\IO\NullIO;
use Composer\Repository\CompositeRepository;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\RepositorySet;
use Php\Pie\DependencyResolver\ResolveDependencyWithComposer;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use Php\Pie\TargetPhp\PhpBinaryPath;
use Php\Pie\TargetPhp\ResolveTargetPhpToPlatformRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResolveDependencyWithComposer::class)]
final class ResolveDependencyWithComposerTest extends TestCase
{
    private RepositorySet $repositorySet;
    private ResolveTargetPhpToPlatformRepository $resolveTargetPhpToPlatformRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->repositorySet = new RepositorySet();
        $this->repositorySet->addRepository(new CompositeRepository(RepositoryFactory::defaultReposWithDefaultManager(new NullIO())));

        $this->resolveTargetPhpToPlatformRepository = new ResolveTargetPhpToPlatformRepository();
    }

    public function testPackageThatCanBeResolved(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('version')
            ->willReturn('8.3.0');

        $package = (new ResolveDependencyWithComposer(
            $this->repositorySet,
            $this->resolveTargetPhpToPlatformRepository,
        ))($phpBinaryPath, 'asgrim/example-pie-extension', '1.0.0');

        self::assertSame('asgrim/example-pie-extension', $package->name);
        self::assertSame('1.0.0', $package->version);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string, 2: string}>
     *
     * @psalm-suppress PossiblyUnusedMethod
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

        $this->expectException(UnableToResolveRequirement::class);

        (new ResolveDependencyWithComposer(
            $this->repositorySet,
            $this->resolveTargetPhpToPlatformRepository,
        ))(
            $phpBinaryPath,
            $package,
            $version,
        );
    }
}
