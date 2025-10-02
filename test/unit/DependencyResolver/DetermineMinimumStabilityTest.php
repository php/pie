<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Php\Pie\DependencyResolver\DetermineMinimumStability;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_combine;
use function array_map;

#[CoversClass(DetermineMinimumStability::class)]
final class DetermineMinimumStabilityTest extends TestCase
{
    /** @return array<string, array{0: RequestedPackageAndVersion, 1: non-empty-string}> */
    public static function requestedVersionToStabilityProvider(): array
    {
        $providerCases = [
            [new RequestedPackageAndVersion('foo/bar', '1.2.3'), 'stable'],
            [new RequestedPackageAndVersion('foo/bar', '1.2.3@stable'), 'stable'],
            [new RequestedPackageAndVersion('foo/bar', '1.2.3@RC'), 'RC'],
            [new RequestedPackageAndVersion('foo/bar', '1.2.3@rc'), 'RC'],
            [new RequestedPackageAndVersion('foo/bar', '1.2.3@beta'), 'beta'],
            [new RequestedPackageAndVersion('foo/bar', '1.2.3@alpha'), 'alpha'],
            [new RequestedPackageAndVersion('foo/bar', '1.2.3@dev'), 'dev'],
            [new RequestedPackageAndVersion('foo/bar', '*@stable'), 'stable'],
            [new RequestedPackageAndVersion('foo/bar', '*@RC'), 'RC'],
            [new RequestedPackageAndVersion('foo/bar', '*@rc'), 'RC'],
            [new RequestedPackageAndVersion('foo/bar', '*@beta'), 'beta'],
            [new RequestedPackageAndVersion('foo/bar', '*@alpha'), 'alpha'],
            [new RequestedPackageAndVersion('foo/bar', '*@dev'), 'dev'],
            [new RequestedPackageAndVersion('foo/bar', 'dev-main'), 'dev'],
            [new RequestedPackageAndVersion('foo/bar', 'v1.x-dev'), 'dev'],
            [new RequestedPackageAndVersion('php/bz2', null), 'dev'],
            [new RequestedPackageAndVersion('php/bz2', '*@stable'), 'stable'],
            [new RequestedPackageAndVersion('php/bz2', '*@RC'), 'RC'],
            [new RequestedPackageAndVersion('php/bz2', '*@beta'), 'beta'],
            [new RequestedPackageAndVersion('php/bz2', '*@alpha'), 'alpha'],
            [new RequestedPackageAndVersion('php/bz2', '*@dev'), 'dev'],
        ];

        return array_combine(
            array_map(
                static fn (array $case) => $case[0]->package . ':' . ($case[0]->version ?? 'null'),
                $providerCases,
            ),
            $providerCases,
        );
    }

    #[DataProvider('requestedVersionToStabilityProvider')]
    public function testFromRequestedVersion(RequestedPackageAndVersion $requestedPackageAndVersion, string $expectedStability): void
    {
        self::assertSame(
            $expectedStability,
            DetermineMinimumStability::fromRequestedVersion($requestedPackageAndVersion),
        );
    }
}
