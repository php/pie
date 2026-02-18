<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Composer\Downloader\TransportException;
use Composer\Package\CompletePackageInterface;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadUrlMethod;
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
use Php\Pie\Platform\WindowsExtensionAssetName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function str_contains;
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
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'asgrim/example-pie-extension',
            '1.2.3',
            'https://test-uri/' . uniqid('downloadUrl', true),
        );

        $releaseAssets = new GithubPackageReleaseAssets('https://test-github-api-base-url.thephp.foundation');

        self::assertSame(
            'actual_download_url',
            $releaseAssets->findMatchingReleaseAssetUrl(
                $targetPlatform,
                $package,
                $httpDownloader,
                DownloadUrlMethod::WindowsBinaryDownload,
                WindowsExtensionAssetName::zipNames(
                    $targetPlatform,
                    $package,
                ),
            ),
        );
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
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'asgrim/example-pie-extension',
            '1.2.3',
            'https://test-uri/' . uniqid('downloadUrl', true),
        );

        $releaseAssets = new GithubPackageReleaseAssets('https://test-github-api-base-url.thephp.foundation');

        self::assertSame(
            'actual_download_url',
            $releaseAssets->findMatchingReleaseAssetUrl(
                $targetPlatform,
                $package,
                $httpDownloader,
                DownloadUrlMethod::WindowsBinaryDownload,
                WindowsExtensionAssetName::zipNames(
                    $targetPlatform,
                    $package,
                ),
            ),
        );
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

        $e = new TransportException('not found', 404);
        $e->setStatusCode(404);

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader
            ->expects(self::atLeastOnce())
            ->method('get')
            ->willThrowException($e);

        $package = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'asgrim/example-pie-extension',
            '1.2.3',
            'https://test-uri/' . uniqid('downloadUrl', true),
        );

        $releaseAssets = new GithubPackageReleaseAssets('https://test-github-api-base-url.thephp.foundation');

        $this->expectException(CouldNotFindReleaseAsset::class);
        $releaseAssets->findMatchingReleaseAssetUrl(
            $targetPlatform,
            $package,
            $httpDownloader,
            DownloadUrlMethod::WindowsBinaryDownload,
            WindowsExtensionAssetName::zipNames(
                $targetPlatform,
                $package,
            ),
        );
    }

    public function testFallsBackToPhpNetWhenGithubReleaseHasNoMatchingAsset(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('majorMinorVersion')
            ->willReturn('8.5');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::Windows,
            OperatingSystemFamily::Windows,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            WindowsCompiler::VS17,
        );

        // GitHub release exists but has no matching Windows asset
        $githubResponse = $this->createMock(Response::class);
        $githubResponse
            ->method('decodeJson')
            ->willReturn(['assets' => []]);

        // downloads.php.net HEAD response succeeds
        $phpNetResponse = $this->createMock(Response::class);
        $phpNetResponse
            ->method('getStatusCode')
            ->willReturn(200);

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader
            ->method('get')
            ->willReturnCallback(static function (string $url) use ($githubResponse, $phpNetResponse): Response {
                if (str_contains($url, 'github')) {
                    return $githubResponse;
                }

                // The fallback should hit downloads.php.net
                self::assertStringStartsWith('https://downloads.php.net/~windows/pecl/releases/apcu/5.1.28/', $url);

                return $phpNetResponse;
            });

        $package = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('apcu'),
            'apcu/apcu',
            'v5.1.28',
            'https://test-uri/' . uniqid('downloadUrl', true),
        );

        $releaseAssets = new GithubPackageReleaseAssets('https://test-github-api-base-url.thephp.foundation');

        $url = $releaseAssets->findMatchingReleaseAssetUrl(
            $targetPlatform,
            $package,
            $httpDownloader,
            DownloadUrlMethod::WindowsBinaryDownload,
            WindowsExtensionAssetName::zipNames($targetPlatform, $package),
        );

        self::assertStringStartsWith('https://downloads.php.net/~windows/pecl/releases/apcu/5.1.28/', $url);
    }

    public function testPhpNetFallbackIsNotAttemptedForNonWindowsDownloadMethods(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('majorMinorVersion')
            ->willReturn('8.5');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            null,
        );

        // GitHub release exists but has no matching asset
        $githubResponse = $this->createMock(Response::class);
        $githubResponse
            ->method('decodeJson')
            ->willReturn(['assets' => []]);

        // Only one HTTP call should be made (to GitHub) — no fallback to downloads.php.net
        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader
            ->expects(self::once())
            ->method('get')
            ->willReturn($githubResponse);

        $package = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'asgrim/example-pie-extension',
            'v1.2.3',
            'https://test-uri/' . uniqid('downloadUrl', true),
        );

        $releaseAssets = new GithubPackageReleaseAssets('https://test-github-api-base-url.thephp.foundation');

        $this->expectException(CouldNotFindReleaseAsset::class);
        $releaseAssets->findMatchingReleaseAssetUrl(
            $targetPlatform,
            $package,
            $httpDownloader,
            DownloadUrlMethod::PrePackagedSourceDownload,
            ['foo-v1.2.3.tgz'],
        );
    }
}
