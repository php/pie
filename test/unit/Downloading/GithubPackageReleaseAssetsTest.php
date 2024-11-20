<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Composer\Downloader\TransportException;
use Composer\Package\CompletePackage;
use Composer\Util\AuthHelper;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\Exception\CouldNotFindReleaseAsset;
use Php\Pie\Downloading\GithubPackageReleaseAssets;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Platform\WindowsCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function uniqid;

#[CoversClass(GithubPackageReleaseAssets::class)]
final class GithubPackageReleaseAssetsTest extends TestCase
{
    public function testUrlIsReturnedWhenFindingWindowsDownloadUrl(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('majorMinorVersion')
            ->willReturn('8.3');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::Windows,
            OperatingSystemFamily::Windows,
            $phpBinaryPath,
            Architecture::x86,
            ThreadSafetyMode::ThreadSafe,
            1,
            WindowsCompiler::VC14,
        );

        $authHelper = $this->createMock(AuthHelper::class);

        $httpDownloaderResponse = $this->createMock(Response::class);
        $httpDownloaderResponse
            ->expects(self::once())
            ->method('decodeJson')
            ->willReturn([
                'assets' => [
                    [
                        'name' => 'php_foo-1.2.3-8.3-vc14-nts-x86.zip',
                        'browser_download_url' => 'wrong_download_url',
                    ],
                    [
                        'name' => 'php_foo-1.2.3-8.3-vc14-ts-x86.zip',
                        'browser_download_url' => 'actual_download_url',
                    ],
                ],
            ]);

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader
            ->expects(self::once())
            ->method('get')
            ->willReturn($httpDownloaderResponse);

        $package = new Package(
            $this->createMock(CompletePackage::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'asgrim/example-pie-extension',
            '1.2.3',
            'https://test-uri/' . uniqid('downloadUrl', true),
            [],
            true,
            true,
            null,
            [],
            [],
        );

        $releaseAssets = new GithubPackageReleaseAssets('https://test-github-api-base-url.thephp.foundation');

        self::assertSame('actual_download_url', $releaseAssets->findWindowsDownloadUrlForPackage($targetPlatform, $package, $authHelper, $httpDownloader));
    }

    public function testUrlIsReturnedWhenFindingWindowsDownloadUrlWithCompilerAndThreadSafetySwapped(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('majorMinorVersion')
            ->willReturn('8.3');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::Windows,
            OperatingSystemFamily::Windows,
            $phpBinaryPath,
            Architecture::x86,
            ThreadSafetyMode::ThreadSafe,
            1,
            WindowsCompiler::VC14,
        );

        $authHelper = $this->createMock(AuthHelper::class);

        $httpDownloaderResponse = $this->createMock(Response::class);
        $httpDownloaderResponse
            ->expects(self::once())
            ->method('decodeJson')
            ->willReturn([
                'assets' => [
                    [
                        'name' => 'php_foo-1.2.3-8.3-nts-vc14-x86.zip',
                        'browser_download_url' => 'wrong_download_url',
                    ],
                    [
                        'name' => 'php_foo-1.2.3-8.3-ts-vc14-x86.zip',
                        'browser_download_url' => 'actual_download_url',
                    ],
                ],
            ]);

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader
            ->expects(self::once())
            ->method('get')
            ->willReturn($httpDownloaderResponse);

        $package = new Package(
            $this->createMock(CompletePackage::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'asgrim/example-pie-extension',
            '1.2.3',
            'https://test-uri/' . uniqid('downloadUrl', true),
            [],
            true,
            true,
            null,
            [],
            [],
        );

        $releaseAssets = new GithubPackageReleaseAssets('https://test-github-api-base-url.thephp.foundation');

        self::assertSame('actual_download_url', $releaseAssets->findWindowsDownloadUrlForPackage($targetPlatform, $package, $authHelper, $httpDownloader));
    }

    public function testFindWindowsDownloadUrlForPackageThrowsExceptionWhenAssetNotFound(): void
    {
        $targetPlatform = new TargetPlatform(
            OperatingSystem::Windows,
            OperatingSystemFamily::Windows,
            PhpBinaryPath::fromCurrentProcess(),
            Architecture::x86,
            ThreadSafetyMode::ThreadSafe,
            1,
            WindowsCompiler::VC14,
        );

        $authHelper = $this->createMock(AuthHelper::class);

        $e = new TransportException('not found', 404);
        $e->setStatusCode(404);

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader
            ->expects(self::once())
            ->method('get')
            ->willThrowException($e);

        $package = new Package(
            $this->createMock(CompletePackage::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'asgrim/example-pie-extension',
            '1.2.3',
            'https://test-uri/' . uniqid('downloadUrl', true),
            [],
            true,
            true,
            null,
            [],
            [],
        );

        $releaseAssets = new GithubPackageReleaseAssets('https://test-github-api-base-url.thephp.foundation');

        $this->expectException(CouldNotFindReleaseAsset::class);
        $releaseAssets->findWindowsDownloadUrlForPackage($targetPlatform, $package, $authHelper, $httpDownloader);
    }
}
