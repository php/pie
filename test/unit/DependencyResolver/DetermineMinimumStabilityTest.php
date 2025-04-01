<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Php\Pie\DependencyResolver\DetermineMinimumStability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_combine;
use function array_map;

#[CoversClass(DetermineMinimumStability::class)]
final class DetermineMinimumStabilityTest extends TestCase
{
    /** @return array<string, array{0: non-empty-string|null, 1: non-empty-string}> */
    public static function requestedVersionToStabilityProvider(): array
    {
        $providerCases = [
            [null, 'stable'],
            ['1.2.3', 'stable'],
            ['1.2.3@stable', 'stable'],
            ['1.2.3@RC', 'RC'],
            ['1.2.3@rc', 'RC'],
            ['1.2.3@beta', 'beta'],
            ['1.2.3@alpha', 'alpha'],
            ['1.2.3@dev', 'dev'],
            ['*@stable', 'stable'],
            ['*@RC', 'RC'],
            ['*@rc', 'RC'],
            ['*@beta', 'beta'],
            ['*@alpha', 'alpha'],
            ['*@dev', 'dev'],
            ['dev-main', 'dev'],
            ['v1.x-dev', 'dev'],
        ];

        return array_combine(
            array_map(
                static fn (array $case) => $case[0] ?? 'null',
                $providerCases,
            ),
            $providerCases,
        );
    }

    #[DataProvider('requestedVersionToStabilityProvider')]
    public function testFromRequestedVersion(string|null $requestedVersion, string $expectedStability): void
    {
        self::assertSame(
            $expectedStability,
            DetermineMinimumStability::fromRequestedVersion($requestedVersion),
        );
    }
}
