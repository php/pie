<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing;

use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\PackageMetadataMissing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PackageMetadataMissing::class)]
final class PackageMetadataMissingTest extends TestCase
{
    public function testDuringUninstall(): void
    {
        $package = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foobar'),
            'foo/bar',
            '1.2.3',
            null,
        );

        $exception = PackageMetadataMissing::duringUninstall(
            $package,
            [
                'a' => 'something',
                'b' => 'something else',
            ],
            ['b', 'c', 'd'],
        );

        self::assertSame(
            'PIE metadata was missing for package foo/bar. Missing metadata keys: c, d',
            $exception->getMessage(),
        );
    }
}
