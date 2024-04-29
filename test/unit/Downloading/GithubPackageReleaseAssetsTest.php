<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Composer\Util\AuthHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\Exception\CouldNotFindReleaseAsset;
use Php\Pie\Downloading\GithubPackageReleaseAssets;
use Php\Pie\ExtensionName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function uniqid;

#[CoversClass(GithubPackageReleaseAssets::class)]
final class GithubPackageReleaseAssetsTest extends TestCase
{
    public function testUrlIsReturnedWhenFindingWindowsDownloadUrl(): void
    {
        $authHelper = $this->createMock(AuthHelper::class);

        $mockHandler = new MockHandler([
            new Response(
                200,
                [],
                json_encode([
                    'assets' => [
                        [
                            'name' => 'php_example_pie_extension-1.2.3-8.3-vs16-nts-x86.zip',
                            'browser_download_url' => 'actual_download_url',
                        ],
                    ],
                ]),
            ),
        ]);

        $guzzleMockClient = new Client(['handler' => HandlerStack::create($mockHandler)]);

        $package = new Package(ExtensionName::normaliseFromString('foo'), 'asgrim/example-pie-extension', '1.2.3', 'https://test-uri/' . uniqid('downloadUrl', true));

        $releaseAssets = new GithubPackageReleaseAssets($authHelper, $guzzleMockClient, 'https://test-github-api-base-url.thephp.foundation');

        self::assertSame('actual_download_url', $releaseAssets->findWindowsDownloadUrlForPackage($package));
    }

    public function testFindWindowsDownloadUrlForPackageThrowsExceptionWhenAssetNotFound(): void
    {
        $authHelper = $this->createMock(AuthHelper::class);

        $mockHandler = new MockHandler([
            new Response(
                200,
                [],
                json_encode([
                    'assets' => [],
                ]),
            ),
        ]);

        $guzzleMockClient = new Client(['handler' => HandlerStack::create($mockHandler)]);

        $package = new Package(ExtensionName::normaliseFromString('foo'), 'asgrim/example-pie-extension', '1.2.3', 'https://test-uri/' . uniqid('downloadUrl', true));

        $releaseAssets = new GithubPackageReleaseAssets($authHelper, $guzzleMockClient, 'https://test-github-api-base-url.thephp.foundation');

        $this->expectException(CouldNotFindReleaseAsset::class);
        $releaseAssets->findWindowsDownloadUrlForPackage($package);
    }
}
