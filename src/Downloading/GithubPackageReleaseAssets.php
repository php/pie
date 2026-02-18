<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Downloader\TransportException;
use Composer\Util\HttpDownloader;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Platform\TargetPlatform;
use Webmozart\Assert\Assert;

use function array_map;
use function in_array;
use function ltrim;
use function strtolower;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class GithubPackageReleaseAssets implements PackageReleaseAssets
{
    public function __construct(
        private readonly string $githubApiBaseUrl,
    ) {
    }

    /**
     * @param non-empty-list<non-empty-string> $possibleReleaseAssetNames
     *
     * @return non-empty-string
     */
    public function findMatchingReleaseAssetUrl(
        TargetPlatform $targetPlatform,
        Package $package,
        HttpDownloader $httpDownloader,
        DownloadUrlMethod $downloadUrlMethod,
        array $possibleReleaseAssetNames,
    ): string {
        try {
            $releaseAsset = $this->selectMatchingReleaseAsset(
                $targetPlatform,
                $package,
                $this->getReleaseAssetsForPackage($package, $httpDownloader, $downloadUrlMethod),
                $downloadUrlMethod,
                $possibleReleaseAssetNames,
            );

            return $releaseAsset['browser_download_url'];
        } catch (Exception\CouldNotFindReleaseAsset $githubException) {
            // GitHub release had no matching asset — try downloads.php.net as a fallback
            // for Windows binaries, since many PECL extensions publish prebuilt DLLs there.
            if ($downloadUrlMethod === DownloadUrlMethod::WindowsBinaryDownload) {
                $fallbackUrl = $this->tryPhpNetWindowsDownload($package, $httpDownloader, $possibleReleaseAssetNames);
                if ($fallbackUrl !== null) {
                    return $fallbackUrl;
                }
            }

            throw $githubException;
        }
    }

    /** @link https://github.com/squizlabs/PHP_CodeSniffer/issues/3734 */
    // phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName
    /**
     * @param list<array{name: non-empty-string, browser_download_url: non-empty-string, ...}> $releaseAssets
     * @param non-empty-list<non-empty-string> $possibleReleaseAssetNames
     *
     * @return array{name: non-empty-string, browser_download_url: non-empty-string, ...}
     */
    // phpcs:enable
    private function selectMatchingReleaseAsset(
        TargetPlatform $targetPlatform,
        Package $package,
        array $releaseAssets,
        DownloadUrlMethod $downloadUrlMethod,
        array $possibleReleaseAssetNames,
    ): array {
        foreach ($releaseAssets as $releaseAsset) {
            if (in_array(strtolower($releaseAsset['name']), $possibleReleaseAssetNames, true)) {
                return $releaseAsset;
            }
        }

        throw Exception\CouldNotFindReleaseAsset::forPackage($targetPlatform, $package, $downloadUrlMethod, $possibleReleaseAssetNames);
    }

    /** @return list<array{name: non-empty-string, browser_download_url: non-empty-string, ...}> */
    private function getReleaseAssetsForPackage(
        Package $package,
        HttpDownloader $httpDownloader,
        DownloadUrlMethod $downloadUrlMethod,
    ): array {
        Assert::notNull($package->downloadUrl());

        try {
            $decodedResponse = $httpDownloader->get(
                $this->githubApiBaseUrl . '/repos/' . $package->githubOrgAndRepository() . '/releases/tags/' . $package->version(),
                [
                    'retry-auth-failure' => true,
                    'http' => [
                        'method' => 'GET',
                        'header' => [],
                    ],
                ],
            )->decodeJson();
        } catch (TransportException $t) {
            /** @link https://docs.github.com/en/rest/releases/releases?apiVersion=2022-11-28#get-a-release-by-tag-name */
            if ($t->getStatusCode() === 404) {
                throw Exception\CouldNotFindReleaseAsset::forPackageWithMissingTag($package, $downloadUrlMethod);
            }

            throw $t;
        }

        Assert::isArray($decodedResponse);
        Assert::keyExists($decodedResponse, 'assets');
        Assert::isList($decodedResponse['assets']);

        return array_map(
            static function (array $asset): array {
                Assert::keyExists($asset, 'name');
                Assert::stringNotEmpty($asset['name']);
                Assert::keyExists($asset, 'browser_download_url');
                Assert::stringNotEmpty($asset['browser_download_url']);

                return $asset;
            },
            $decodedResponse['assets'],
        );
    }

    /**
     * Fallback: attempt to find a prebuilt Windows extension archive on
     * downloads.php.net, which hosts PECL binaries that may not be attached
     * to GitHub releases.
     *
     * URL pattern: https://downloads.php.net/~windows/pecl/releases/{ext}/{version}/{asset}
     *
     * @param non-empty-list<non-empty-string> $possibleReleaseAssetNames
     *
     * @return non-empty-string|null
     */
    private function tryPhpNetWindowsDownload(
        Package $package,
        HttpDownloader $httpDownloader,
        array $possibleReleaseAssetNames,
    ): string|null {
        $extName         = $package->extensionName()->name();
        $versionWithoutV = ltrim($package->version(), 'vV');

        foreach ($possibleReleaseAssetNames as $assetName) {
            $url = 'https://downloads.php.net/~windows/pecl/releases/'
                . $extName . '/' . $versionWithoutV . '/' . $assetName;

            try {
                $response = $httpDownloader->get($url, [
                    'http' => ['method' => 'HEAD'],
                ]);

                if ($response->getStatusCode() === 200) {
                    return $url;
                }
            } catch (TransportException) {
                // Asset not found at this URL, try next variant
                continue;
            }
        }

        return null;
    }
}
