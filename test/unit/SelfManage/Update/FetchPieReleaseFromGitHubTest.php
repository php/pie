<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\Update;

use Composer\Util\AuthHelper;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Php\Pie\SelfManage\Update\Channel;
use Php\Pie\SelfManage\Update\FetchPieReleaseFromGitHub;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_get_contents;
use function hash;
use function json_encode;
use function uniqid;

#[CoversClass(FetchPieReleaseFromGitHub::class)]
final class FetchPieReleaseFromGitHubTest extends TestCase
{
    private const TEST_GITHUB_URL    = 'http://test-github-url.localhost';
    private const TEST_RELEASES_LIST = [
        [
            'tag_name' => '1.2.4-rc1',
            'assets' => [
                [
                    'name' => 'not-pie.phar',
                    'browser_download_url' => self::TEST_GITHUB_URL . '/do/not/download/this',
                ],
                [
                    'name' => 'pie.phar',
                    'browser_download_url' => self::TEST_GITHUB_URL . '/path/to/pie.phar',
                ],
            ],
        ],
        [
            'tag_name' => '1.2.3',
            'assets' => [
                [
                    'name' => 'not-pie.phar',
                    'browser_download_url' => self::TEST_GITHUB_URL . '/do/not/download/this',
                ],
                [
                    'name' => 'pie.phar',
                    'browser_download_url' => self::TEST_GITHUB_URL . '/path/to/pie.phar',
                ],
            ],
        ],
    ];

    public function testLatestReleaseMetadataForStableChannel(): void
    {
        $httpDownloader = $this->createMock(HttpDownloader::class);
        $authHelper     = $this->createMock(AuthHelper::class);

        $url = self::TEST_GITHUB_URL . '/repos/php/pie/releases';
        $authHelper
            ->method('addAuthenticationHeader')
            ->willReturn(['Authorization: Bearer fake-token']);
        $httpDownloader->expects(self::once())
            ->method('get')
            ->with(
                $url,
                [
                    'retry-auth-failure' => true,
                    'http' => [
                        'method' => 'GET',
                        'header' => ['Authorization: Bearer fake-token'],
                    ],
                ],
            )
            ->willReturn(
                new Response(
                    ['url' => $url],
                    200,
                    [],
                    (string) json_encode(self::TEST_RELEASES_LIST),
                ),
            );

        $fetch = new FetchPieReleaseFromGitHub(self::TEST_GITHUB_URL, $httpDownloader, $authHelper);

        $latestRelease = $fetch->latestReleaseMetadata(Channel::Stable);

        self::assertSame('1.2.3', $latestRelease->tag);
        self::assertSame(self::TEST_GITHUB_URL . '/path/to/pie.phar', $latestRelease->downloadUrl);
    }

    public function testLatestReleaseMetadataForPreviewChannel(): void
    {
        $httpDownloader = $this->createMock(HttpDownloader::class);
        $authHelper     = $this->createMock(AuthHelper::class);

        $url = self::TEST_GITHUB_URL . '/repos/php/pie/releases';
        $authHelper
            ->method('addAuthenticationHeader')
            ->willReturn(['Authorization: Bearer fake-token']);
        $httpDownloader->expects(self::once())
            ->method('get')
            ->with(
                $url,
                [
                    'retry-auth-failure' => true,
                    'http' => [
                        'method' => 'GET',
                        'header' => ['Authorization: Bearer fake-token'],
                    ],
                ],
            )
            ->willReturn(
                new Response(
                    ['url' => $url],
                    200,
                    [],
                    (string) json_encode(self::TEST_RELEASES_LIST),
                ),
            );

        $fetch = new FetchPieReleaseFromGitHub(self::TEST_GITHUB_URL, $httpDownloader, $authHelper);

        $latestRelease = $fetch->latestReleaseMetadata(Channel::Preview);

        self::assertSame('1.2.4-rc1', $latestRelease->tag);
        self::assertSame(self::TEST_GITHUB_URL . '/path/to/pie.phar', $latestRelease->downloadUrl);
    }

    public function testLatestReleaseNotHavingPiePharThrowsException(): void
    {
        $httpDownloader = $this->createMock(HttpDownloader::class);
        $authHelper     = $this->createMock(AuthHelper::class);

        $url = self::TEST_GITHUB_URL . '/repos/php/pie/releases';
        $authHelper
            ->method('addAuthenticationHeader')
            ->willReturn(['Authorization: Bearer fake-token']);
        $httpDownloader->expects(self::once())
            ->method('get')
            ->with(
                $url,
                [
                    'retry-auth-failure' => true,
                    'http' => [
                        'method' => 'GET',
                        'header' => ['Authorization: Bearer fake-token'],
                    ],
                ],
            )
            ->willReturn(
                new Response(
                    ['url' => $url],
                    200,
                    [],
                    json_encode([
                        [
                            'tag_name' => '1.2.3',
                            'assets' => [
                                [
                                    'name' => 'not-pie.phar',
                                    'browser_download_url' => self::TEST_GITHUB_URL . '/do/not/download/this',
                                ],
                            ],
                        ],
                        [
                            'tag_name' => '1.2.4-rc1',
                            'assets' => [
                                [
                                    'name' => 'not-pie.phar',
                                    'browser_download_url' => self::TEST_GITHUB_URL . '/do/not/download/this',
                                ],
                            ],
                        ],
                    ]),
                ),
            );

        $fetch = new FetchPieReleaseFromGitHub(self::TEST_GITHUB_URL, $httpDownloader, $authHelper);

        $this->expectException(RuntimeException::class);
        $fetch->latestReleaseMetadata(Channel::Stable);
    }

    public function testDownloadContent(): void
    {
        $url            = self::TEST_GITHUB_URL . '/path/to/pie.phar';
        $pharContent    = uniqid('pharContent', true);
        $expectedDigest = hash('sha256', $pharContent);

        $latestRelease = new ReleaseMetadata('1.2.3', $url);

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $authHelper     = $this->createMock(AuthHelper::class);

        $authHelper
            ->method('addAuthenticationHeader')
            ->willReturn(['Authorization: Bearer fake-token']);
        $httpDownloader->expects(self::once())
            ->method('get')
            ->with(
                $url,
                [
                    'retry-auth-failure' => true,
                    'http' => [
                        'method' => 'GET',
                        'header' => ['Authorization: Bearer fake-token'],
                    ],
                ],
            )
            ->willReturn(
                new Response(
                    ['url' => $url],
                    200,
                    [],
                    $pharContent,
                ),
            );

        $fetch = new FetchPieReleaseFromGitHub(self::TEST_GITHUB_URL, $httpDownloader, $authHelper);

        $file = $fetch->downloadContent($latestRelease);

        self::assertSame($pharContent, file_get_contents($file->filePath));
        self::assertSame($expectedDigest, $file->checksum);
    }
}
