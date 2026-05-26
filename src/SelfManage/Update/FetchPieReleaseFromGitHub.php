<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Update;

use Composer\Config;
use Composer\Package\Version\VersionParser;
use Composer\Util\HttpDownloader;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\File\BinaryFile;
use RuntimeException;
use Webmozart\Assert\Assert;

use function array_filter;
use function array_key_exists;
use function array_map;
use function count;
use function file_put_contents;
use function preg_match;
use function reset;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class FetchPieReleaseFromGitHub implements FetchPieRelease
{
    private const PIE_PHAR_NAME    = 'pie.phar';
    private const PIE_REPO_URL     = '/repos/php/pie';
    private const PIE_RELEASES_URL = '/repos/php/pie/releases';

    public function __construct(
        private readonly string $githubApiBaseUrl,
        private readonly HttpDownloader $httpDownloader,
    ) {
    }

    public static function factory(QuieterConsoleIO $io, Config $config, string $githubApiBaseUrl): self
    {
        return new self($githubApiBaseUrl, new HttpDownloader($io, $config));
    }

    public function trunkBranch(): string
    {
        $url = $this->githubApiBaseUrl . self::PIE_REPO_URL;

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

        Assert::isArray($decodedResponse);
        Assert::keyExists($decodedResponse, 'default_branch');
        Assert::stringNotEmpty($decodedResponse['default_branch']);

        $branch = $decodedResponse['default_branch'];

        // Branch MUST match the N.N.x format
        if (preg_match('/^\d+\.\d+\.x$/', $branch) !== 1) {
            throw new RuntimeException(sprintf(
                'The default branch "%s" returned by GitHub is not in an expected format.',
                $branch,
            ));
        }

        return $branch;
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
                array_filter(
                    $decodedResponse,
                    static fn (array $releaseResponse): bool => (! array_key_exists('draft', $releaseResponse) || ! $releaseResponse['draft']),
                ),
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
