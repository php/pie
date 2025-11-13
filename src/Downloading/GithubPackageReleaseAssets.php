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
        array $possibleReleaseAssetNames,
    ): string {
        $releaseAsset = $this->selectMatchingReleaseAsset(
            $targetPlatform,
            $package,
            $this->getReleaseAssetsForPackage($package, $httpDownloader),
            $possibleReleaseAssetNames,
        );

        return $releaseAsset['browser_download_url'];
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
        array $possibleReleaseAssetNames,
    ): array {
        foreach ($releaseAssets as $releaseAsset) {
            if (in_array(strtolower($releaseAsset['name']), $possibleReleaseAssetNames, true)) {
                return $releaseAsset;
            }
        }

        throw Exception\CouldNotFindReleaseAsset::forPackage($targetPlatform, $package, $possibleReleaseAssetNames);
    }

    /** @return list<array{name: non-empty-string, browser_download_url: non-empty-string, ...}> */
    private function getReleaseAssetsForPackage(
        Package $package,
        HttpDownloader $httpDownloader,
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
                throw Exception\CouldNotFindReleaseAsset::forPackageWithMissingTag($package);
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
}
