<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Composer\Util\AuthHelper;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadZip;
use Php\Pie\Downloading\ExtractZip;
use Php\Pie\Downloading\UnixDownloadAndExtract;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function uniqid;

#[CoversClass(UnixDownloadAndExtract::class)]
final class UnixDownloadAndExtractTest extends TestCase
{
    public function testInvoke(): void
    {
        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            PhpBinaryPath::fromCurrentProcess(),
            Architecture::x86,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
        );

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
        $requestedPackage = new Package(
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'foo/bar',
            '1.2.3',
            $downloadUrl,
            [],
            null,
            '1.2.3.0',
            true,
            true,
            '',
        );

        $downloadedPackage = $unixDownloadAndExtract->__invoke($targetPlatform, $requestedPackage);

        self::assertSame($requestedPackage, $downloadedPackage->package);
        self::assertSame($extractedPath, $downloadedPackage->getSourcePath());
    }
}
