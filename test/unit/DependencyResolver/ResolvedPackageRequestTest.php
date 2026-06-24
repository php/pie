<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\DependencyResolver\ResolvedPackageRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(ResolvedPackageRequest::class)]
final class ResolvedPackageRequestTest extends TestCase
{
    private static function packageNamed(string $prettyName, string $prettyVersion): Package
    {
        $composerPackage = self::createStub(CompletePackageInterface::class);
        $composerPackage->method('getPrettyName')->willReturn($prettyName);
        $composerPackage->method('getPrettyVersion')->willReturn($prettyVersion);
        $composerPackage->method('getType')->willReturn('php-ext');

        return Package::fromComposerCompletePackage($composerPackage);
    }

    public function testExtensionNames(): void
    {
        $resolvedPackageRequests = [
            new ResolvedPackageRequest(self::packageNamed('foo/bar', '1.0.0'), new RequestedPackageAndVersion('foo/bar', null)),
            new ResolvedPackageRequest(self::packageNamed('baz/qux', '2.0.0'), new RequestedPackageAndVersion('baz/qux', '^2.0')),
        ];

        self::assertSame(
            ['bar', 'qux'],
            array_map(static fn ($extensionName) => $extensionName->name(), ResolvedPackageRequest::extensionNames($resolvedPackageRequests)),
        );
    }

    public function testRequestedPackageNames(): void
    {
        $resolvedPackageRequests = [
            new ResolvedPackageRequest(self::packageNamed('foo/bar', '1.0.0'), new RequestedPackageAndVersion('foo/bar', null)),
            new ResolvedPackageRequest(self::packageNamed('baz/qux', '2.0.0'), new RequestedPackageAndVersion('baz/qux', '^2.0')),
        ];

        self::assertSame(
            ['foo/bar', 'baz/qux'],
            ResolvedPackageRequest::requestedPackageNames($resolvedPackageRequests),
        );
    }

    public function testPiePackages(): void
    {
        $packageA = self::packageNamed('foo/bar', '1.0.0');
        $packageB = self::packageNamed('baz/qux', '2.0.0');

        $resolvedPackageRequests = [
            new ResolvedPackageRequest($packageA, new RequestedPackageAndVersion('foo/bar', null)),
            new ResolvedPackageRequest($packageB, new RequestedPackageAndVersion('baz/qux', '^2.0')),
        ];

        self::assertSame(
            [$packageA, $packageB],
            ResolvedPackageRequest::piePackages($resolvedPackageRequests),
        );
    }
}
