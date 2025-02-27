<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Composer\Package\CompletePackage;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function realpath;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(DownloadedPackage::class)]
final class DownloadedPackageTest extends TestCase
{
    public function testFromPackageAndExtractedPath(): void
    {
        $package = new Package(
            $this->createMock(CompletePackage::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'foo/bar',
            '1.2.3',
            null,
        );

        $extractedSourcePath = uniqid('/path/to/downloaded/package', true);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath($package, $extractedSourcePath);

        self::assertSame($extractedSourcePath, $downloadedPackage->extractedSourcePath);
        self::assertSame($package, $downloadedPackage->package);
    }

    public function testFromPackageAndExtractedPathWithBuildPath(): void
    {
        $composerPackage = $this->createMock(CompletePackage::class);
        $composerPackage->method('getPrettyName')->willReturn('foo/bar');
        $composerPackage->method('getPrettyVersion')->willReturn('1.2.3');
        $composerPackage->method('getType')->willReturn('php-ext');
        $composerPackage->method('getPhpExt')->willReturn(['build-path' => 'Downloading']);

        $package = Package::fromComposerCompletePackage($composerPackage);

        $extractedSourcePath = realpath(__DIR__ . '/../');

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath($package, $extractedSourcePath);

        self::assertSame($extractedSourcePath . DIRECTORY_SEPARATOR . 'Downloading', $downloadedPackage->extractedSourcePath);
        self::assertSame($package, $downloadedPackage->package);
    }

    public function testFromPackageAndExtractedPathWithBuildPathWithVersionTemplate(): void
    {
        $composerPackage = $this->createMock(CompletePackage::class);
        $composerPackage->method('getPrettyName')->willReturn('foo/bar');
        $composerPackage->method('getPrettyVersion')->willReturn('1.2.3');
        $composerPackage->method('getType')->willReturn('php-ext');
        $composerPackage->method('getPhpExt')->willReturn(['build-path' => 'package-{version}']);

        $package = Package::fromComposerCompletePackage($composerPackage);

        $extractedSourcePath = realpath(__DIR__ . '/../../assets');

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath($package, $extractedSourcePath);

        self::assertSame($extractedSourcePath . DIRECTORY_SEPARATOR . 'package-1.2.3', $downloadedPackage->extractedSourcePath);
        self::assertSame($package, $downloadedPackage->package);
    }

    public function testBuildPathDetectedFromExtractedPrePackagedSourceAsset(): void
    {
        $composerPackage = $this->createMock(CompletePackage::class);
        $composerPackage->method('getPrettyName')->willReturn('foo/bar');
        $composerPackage->method('getPrettyVersion')->willReturn('1.2.3');
        $composerPackage->method('getType')->willReturn('php-ext');

        $package = Package::fromComposerCompletePackage($composerPackage);

        $extractedSourcePath = realpath(__DIR__ . '/../../assets');

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath($package, $extractedSourcePath);

        self::assertSame($extractedSourcePath . DIRECTORY_SEPARATOR . 'php_bar-1.2.3-src', $downloadedPackage->extractedSourcePath);
        self::assertSame($package, $downloadedPackage->package);
    }
}
