<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Composer\IO\NullIO;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\RepositorySet;
use Php\Pie\DependencyResolver\ResolveDependencyWithComposer;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResolveDependencyWithComposer::class)]
final class ResolveDependencyWithComposerTest extends TestCase
{
    private RepositorySet $repositorySet;

    public function setUp(): void
    {
        parent::setUp();

        $this->repositorySet = new RepositorySet();
        $this->repositorySet->addRepository(new CompositeRepository(RepositoryFactory::defaultReposWithDefaultManager(new NullIO())));
    }

    public function testPackageThatCanBeResolved(): void
    {
        $package = (new ResolveDependencyWithComposer(
            new PlatformRepository([], ['php' => '8.2.0']),
            $this->repositorySet,
        ))('phpunit/phpunit', '^11.0');

        self::assertSame('phpunit/phpunit', $package->name);
    }

    /** @return list<string, array{0: array<array-key, mixed>, 1: string, 2: string}> */
    public static function unresolvableDependencies(): array
    {
        return [
            'phpVersionTooOld' => [['php' => '8.1.0'], 'phpunit/phpunit', '^11.0'],
            'phpVersionTooNew' => [['php' => '8.3.0'], 'roave/signature', '1.4.*'],
        ];
    }

    /** @param array<array-key, mixed> $platformOverrides */
    #[DataProvider('unresolvableDependencies')]
    public function testPackageThatCannotBeResolvedThrowsException(array $platformOverrides, string $package, string $version): void
    {
        $this->expectException(UnableToResolveRequirement::class);

        (new ResolveDependencyWithComposer(
            new PlatformRepository([], $platformOverrides),
            $this->repositorySet,
        ))(
            $package,
            $version,
        );
    }
}
