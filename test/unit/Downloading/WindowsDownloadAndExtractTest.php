<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Composer\Util\AuthHelper;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadZip;
use Php\Pie\Downloading\ExtractZip;
use Php\Pie\Downloading\PackageReleaseAssets;
use Php\Pie\Downloading\WindowsDownloadAndExtract;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(WindowsDownloadAndExtract::class)]
final class WindowsDownloadAndExtractTest extends TestCase
{
    public function testInvoke(): void
    {
        $downloadZip               = $this->createMock(DownloadZip::class);
        $extractZip                = $this->createMock(ExtractZip::class);
        $authHelper                = $this->createMock(AuthHelper::class);
        $packageReleaseAssets      = $this->createMock(PackageReleaseAssets::class);
        $windowsDownloadAndExtract = new WindowsDownloadAndExtract(
            $downloadZip,
            $extractZip,
            $authHelper,
            $packageReleaseAssets,
        );

        $packageReleaseAssets->expects(self::once())
            ->method('findWindowsDownloadUrlForPackage')
            ->with(self::isInstanceOf(Package::class))
            ->willReturn(uniqid('windowsDownloadUrl', true));

        $tmpZipFile    = uniqid('tmpZipFile', true);
        $extractedPath = uniqid('extractedPath', true);

        $downloadZip->expects(self::once())
            ->method('downloadZipAndReturnLocalPath')
            ->with(
                self::isInstanceOf(RequestInterface::class),
                self::isType('string'),
            )
            ->willReturn($tmpZipFile);

        $extractZip->expects(self::once())
            ->method('to')
            ->with(
                $tmpZipFile,
                self::isType('string'),
            )
            ->willReturn($extractedPath);

        $requestedPackage = new Package('foo/bar', '1.2.3', 'https://test-uri/' . uniqid('downloadUrl', true));

        $downloadedPackage = $windowsDownloadAndExtract->__invoke($requestedPackage);

        self::assertSame($requestedPackage, $downloadedPackage->package);
        self::assertStringContainsString(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pie_downloader_', $downloadedPackage->extractedSourcePath);
    }
}
