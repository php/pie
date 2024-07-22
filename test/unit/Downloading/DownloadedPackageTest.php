<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function uniqid;

#[CoversClass(DownloadedPackage::class)]
final class DownloadedPackageTest extends TestCase
{
    public function testFromPackageAndExtractedPath(): void
    {
        $package = new Package(
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'foo/bar',
            '1.2.3',
            null,
            [],
        );

        $extractedSourcePath = uniqid('/path/to/downloaded/package', true);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath($package, $extractedSourcePath);

        self::assertSame($extractedSourcePath, $downloadedPackage->extractedSourcePath);
        self::assertSame($package, $downloadedPackage->package);
    }
}
