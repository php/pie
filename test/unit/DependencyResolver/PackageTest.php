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
            new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3'),
        );

        self::assertSame('foo', $package->extensionName->name());
        self::assertSame('vendor/foo', $package->name);
        self::assertSame('1.2.3', $package->version);
        self::assertSame('vendor/foo:1.2.3', $package->prettyNameAndVersion());
        self::assertNull($package->downloadUrl);
    }

    public function testFromComposerCompletePackageWithExtensionName(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['extension-name' => 'ext-something_else']);

        $package = Package::fromComposerCompletePackage($composerCompletePackage);

        self::assertSame('something_else', $package->extensionName->name());
        self::assertSame('vendor/foo', $package->name);
        self::assertSame('1.2.3', $package->version);
        self::assertSame('vendor/foo:1.2.3', $package->prettyNameAndVersion());
        self::assertNull($package->downloadUrl);
    }
}
