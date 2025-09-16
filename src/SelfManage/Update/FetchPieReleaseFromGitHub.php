<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Update;

use Composer\Util\AuthHelper;
use Composer\Util\HttpDownloader;
use Php\Pie\File\BinaryFile;
use RuntimeException;
use Webmozart\Assert\Assert;

use function array_filter;
use function array_map;
use function count;
use function file_put_contents;
use function reset;
use function sys_get_temp_dir;
use function tempnam;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class FetchPieReleaseFromGitHub implements FetchPieRelease
{
    private const PIE_PHAR_NAME          = 'pie.phar';
    private const PIE_LATEST_RELEASE_URL = '/repos/php/pie/releases/latest';

    public function __construct(
        private readonly string $githubApiBaseUrl,
        private readonly HttpDownloader $httpDownloader,
        private readonly AuthHelper $authHelper,
    ) {
    }

    public function latestReleaseMetadata(): ReleaseMetadata
    {
        $url = $this->githubApiBaseUrl . self::PIE_LATEST_RELEASE_URL;

        $decodedResponse = $this->httpDownloader->get(
            $url,
            [
                'retry-auth-failure' => true,
                'http' => [
                    'method' => 'GET',
                    'header' => $this->authHelper->addAuthenticationHeader([], $this->githubApiBaseUrl, $url),
                ],
            ],
        )->decodeJson();

        Assert::isArray($decodedResponse);
        Assert::keyExists($decodedResponse, 'tag_name');
        Assert::stringNotEmpty($decodedResponse['tag_name']);
        Assert::keyExists($decodedResponse, 'assets');
        Assert::isList($decodedResponse['assets']);

        $assetsNamedPiePhar = array_filter(
            array_map(
                /** @return array{name: non-empty-string, browser_download_url: non-empty-string, ...} */
                static function (array $asset): array {
                    Assert::keyExists($asset, 'name');
                    Assert::stringNotEmpty($asset['name']);
                    Assert::keyExists($asset, 'browser_download_url');
                    Assert::stringNotEmpty($asset['browser_download_url']);

                    return $asset;
                },
                $decodedResponse['assets'],
            ),
            static function (array $asset): bool {
                return $asset['name'] === self::PIE_PHAR_NAME;
            },
        );

        if (! count($assetsNamedPiePhar)) {
            throw PiePharMissingFromLatestRelease::fromRelease($decodedResponse['tag_name']);
        }

        $firstAssetNamedPiePhar = reset($assetsNamedPiePhar);

        return new ReleaseMetadata(
            $decodedResponse['tag_name'],
            $firstAssetNamedPiePhar['browser_download_url'],
        );
    }

    public function downloadContent(ReleaseMetadata $releaseMetadata): BinaryFile
    {
        $pharContent = $this->httpDownloader->get(
            $releaseMetadata->downloadUrl,
            [
                'retry-auth-failure' => true,
                'http' => [
                    'method' => 'GET',
                    'header' => $this->authHelper->addAuthenticationHeader([], $this->githubApiBaseUrl, $releaseMetadata->downloadUrl),
                ],
            ],
        )->getBody();
        Assert::stringNotEmpty($pharContent);

        $tempPharFilename = tempnam(sys_get_temp_dir(), 'pie_self_update_');
        Assert::stringNotEmpty($tempPharFilename);

        if (file_put_contents($tempPharFilename, $pharContent) === false) {
            throw new RuntimeException('Failed to write downloaded PHAR to ' . $tempPharFilename);
        }

        return BinaryFile::fromFileWithSha256Checksum($tempPharFilename);
    }
}
