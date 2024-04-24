<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Composer\Package\CompletePackage;
use Php\Pie\DependencyResolver\Package;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Package::class)]
final class PackageTest extends TestCase
{
    public function testFromComposerCompletePackage(): void
    {
        $package = Package::fromComposerCompletePackage(
            new CompletePackage('foo', '1.2.3.0', '1.2.3'),
        );

        self::assertSame('foo', $package->name);
        self::assertSame('1.2.3', $package->version);
        self::assertSame('foo:1.2.3', $package->prettyNameAndVersion());
        self::assertNull($package->downloadUrl);
    }
}
