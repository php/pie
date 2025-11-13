<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Update;

use Composer\Package\Version\VersionParser;
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
    private const PIE_PHAR_NAME    = 'pie.phar';
    private const PIE_RELEASES_URL = '/repos/php/pie/releases';

    public function __construct(
        private readonly string $githubApiBaseUrl,
        private readonly HttpDownloader $httpDownloader,
    ) {
    }

    public function latestReleaseMetadata(Channel $updateChannel): ReleaseMetadata
    {
        $url = $this->githubApiBaseUrl . self::PIE_RELEASES_URL;

        $decodedResponse = $this->httpDownloader->get(
            $url,
            [
                'retry-auth-failure' => true,
                'http' => [
                    'method' => 'GET',
                    'header' => [],
                ],
            ],
        )->decodeJson();

        Assert::isList($decodedResponse);
        Assert::allIsArray($decodedResponse);

        $releases = array_filter(
            array_map(
                static function (array $releaseResponse): ReleaseMetadata|null {
                    Assert::keyExists($releaseResponse, 'tag_name');
                    Assert::stringNotEmpty($releaseResponse['tag_name']);
                    Assert::keyExists($releaseResponse, 'assets');
                    Assert::isList($releaseResponse['assets']);
                    Assert::allIsArray($releaseResponse['assets']);

                    $assetsNamedPiePhar = array_filter(
                        array_map(
                            static function (array $asset): array {
                                Assert::keyExists($asset, 'name');
                                Assert::stringNotEmpty($asset['name']);
                                Assert::keyExists($asset, 'browser_download_url');
                                Assert::stringNotEmpty($asset['browser_download_url']);

                                return $asset;
                            },
                            $releaseResponse['assets'],
                        ),
                        static function (array $asset): bool {
                            return $asset['name'] === self::PIE_PHAR_NAME;
                        },
                    );

                    if (! count($assetsNamedPiePhar)) {
                        return null;
                    }

                    $firstAssetNamedPiePhar = reset($assetsNamedPiePhar);

                    return new ReleaseMetadata(
                        $releaseResponse['tag_name'],
                        $firstAssetNamedPiePhar['browser_download_url'],
                    );
                },
                $decodedResponse,
            ),
            static function (ReleaseMetadata|null $releaseMetadata) use ($updateChannel): bool {
                if ($releaseMetadata === null) {
                    return false;
                }

                $stability = VersionParser::parseStability($releaseMetadata->tag);

                return ($updateChannel === Channel::Stable && $stability === 'stable')
                    || $updateChannel === Channel::Preview;
            },
        );

        $first = reset($releases);

        if (! $first instanceof ReleaseMetadata) {
            throw new RuntimeException('No PIE release found for channel ' . $updateChannel->value);
        }

        return $first;
    }

    public function downloadContent(ReleaseMetadata $releaseMetadata): BinaryFile
    {
        $pharContent = $this->httpDownloader->get(
            $releaseMetadata->downloadUrl,
            [
                'retry-auth-failure' => true,
                'http' => [
                    'method' => 'GET',
                    'header' => [],
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
