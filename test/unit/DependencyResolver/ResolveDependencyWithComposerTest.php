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
        $phpBinaryPath->expects(self::once())
            ->method('version')
            ->willReturn('8.2.0');

        $package = (new ResolveDependencyWithComposer(
            $this->repositorySet,
            $this->resolveTargetPhpToPlatformRepository,
        ))($phpBinaryPath, 'phpunit/phpunit', '^11.0');

        self::assertSame('phpunit/phpunit', $package->name);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string, 2: string}>
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function unresolvableDependencies(): array
    {
        return [
            'phpVersionTooOld' => [['php' => '8.1.0'], 'phpunit/phpunit', '^11.0'],
            'phpVersionTooNew' => [['php' => '8.3.0'], 'roave/signature', '1.4.*'],
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
