<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Composer\Util\AuthHelper;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadZip;
use Php\Pie\Downloading\ExtractZip;
use Php\Pie\Downloading\UnixDownloadAndExtract;
use Php\Pie\ExtensionName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function uniqid;

#[CoversClass(UnixDownloadAndExtract::class)]
final class UnixDownloadAndExtractTest extends TestCase
{
    public function testInvoke(): void
    {
        $downloadZip            = $this->createMock(DownloadZip::class);
        $extractZip             = $this->createMock(ExtractZip::class);
        $authHelper             = $this->createMock(AuthHelper::class);
        $unixDownloadAndExtract = new UnixDownloadAndExtract($downloadZip, $extractZip, $authHelper);

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

        $downloadUrl      = 'https://test-uri/' . uniqid('downloadUrl', true);
        $requestedPackage = new Package(ExtensionName::normaliseFromString('foo'), 'foo/bar', '1.2.3', $downloadUrl);

        $downloadedPackage = $unixDownloadAndExtract->__invoke($requestedPackage);

        self::assertSame($requestedPackage, $downloadedPackage->package);
        self::assertSame($extractedPath, $downloadedPackage->extractedSourcePath);
    }
}
