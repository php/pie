<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\DependencyResolver;

use Php\Pie\Container;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\ResolveDependencyWithComposer;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResolveDependencyWithComposer::class)]
final class ResolveDependencyWithComposerTest extends TestCase
{
    /**
     * @return array<non-empty-string, array{0: non-empty-string|null, 1: non-empty-string}>
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public static function validVersionsList(): array
    {
        $versionsAndExpected = [
            [null, '1.0.1'],
            ['*', '1.0.1'],
            ['1.0.1-alpha.3@alpha', '1.0.1-alpha.3'],
            ['^1.0', '1.0.1'],
            ['^1.1.0@alpha', '1.1.0-beta.1'],
            ['^1.0@beta', '1.1.0-beta.1'],
            ['^1.1@beta', '1.1.0-beta.1'],
            ['~1.0.0', '1.0.1'],
            ['~1.0.0@alpha', '1.0.1'],
            ['~1.0.0@beta', '1.0.1'],
            ['~1.0@beta', '1.1.0-beta.1'],
            // @todo https://github.com/php/pie/issues/13 - in theory, these could work, on NonWindows at least
//            ['dev-main@dev', 'dev-main'],
//            ['dev-main#769f906413d6d1e12152f6d34134cbcd347ca253@dev', 'dev-main'],
        ];

        return array_combine(
            array_map(static fn ($item) => $item[0], $versionsAndExpected),
            $versionsAndExpected,
        );
    }

    #[DataProvider('validVersionsList')]
    public function testDependenciesAreResolvedToExpectedVersions(string|null $requestedVersion, string $expectedVersion)
    {
        if (PHP_VERSION_ID < 80300 || PHP_VERSION_ID >= 80400) {
            self::markTestSkipped('This test can only run on PHP 8.3 - you are running ' . PHP_VERSION);
        }

        $container = Container::factory();
        $resolve = $container->get(DependencyResolver::class);

        $package = $resolve->__invoke(
            TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess()),
            'asgrim/example-pie-extension',
            $requestedVersion,
        );

        self::assertSame($expectedVersion, $package->version);
    }
}
