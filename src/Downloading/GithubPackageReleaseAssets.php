<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Util\AuthHelper;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\WindowsExtensionAssetName;
use Psr\Http\Message\ResponseInterface;
use Webmozart\Assert\Assert;

use function array_map;
use function assert;
use function in_array;
use function json_decode;
use function strtolower;

use const JSON_BIGINT_AS_STRING;
use const JSON_THROW_ON_ERROR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class GithubPackageReleaseAssets implements PackageReleaseAssets
{
    /** @psalm-api */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $githubApiBaseUrl,
    ) {
    }

    /** @return non-empty-string */
    public function findWindowsDownloadUrlForPackage(
        TargetPlatform $targetPlatform,
        Package $package,
        AuthHelper $authHelper,
    ): string {
        $releaseAsset = $this->selectMatchingReleaseAsset(
            $targetPlatform,
            $package,
            $this->getReleaseAssetsForPackage($package, $authHelper),
        );

        return $releaseAsset['browser_download_url'];
    }

    /** @return non-empty-list<non-empty-string> */
    private function expectedWindowsAssetNames(TargetPlatform $targetPlatform, Package $package): array
    {
        return WindowsExtensionAssetName::zipNames($targetPlatform, $package);
    }

    /** @link https://github.com/squizlabs/PHP_CodeSniffer/issues/3734 */
    // phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName
    /**
     * @param list<array{name: non-empty-string, browser_download_url: non-empty-string, ...}> $releaseAssets
     *
     * @return array{name: non-empty-string, browser_download_url: non-empty-string, ...}
     */
    // phpcs:enable
    private function selectMatchingReleaseAsset(TargetPlatform $targetPlatform, Package $package, array $releaseAssets): array
    {
        $expectedAssetNames = $this->expectedWindowsAssetNames($targetPlatform, $package);

        foreach ($releaseAssets as $releaseAsset) {
            if (in_array(strtolower($releaseAsset['name']), $expectedAssetNames, true)) {
                return $releaseAsset;
            }
        }

        throw Exception\CouldNotFindReleaseAsset::forPackage($package, $expectedAssetNames);
    }

    /** @return list<array{name: non-empty-string, browser_download_url: non-empty-string, ...}> */
    private function getReleaseAssetsForPackage(Package $package, AuthHelper $authHelper): array
    {
        $request = AddAuthenticationHeader::withAuthHeaderFromComposer(
            new Request('GET', $this->githubApiBaseUrl . '/repos/' . $package->githubOrgAndRepository() . '/releases/tags/' . $package->version),
            $package,
            $authHelper,
        );

        $response = $this->client
            ->sendAsync(
                $request,
                [
                    RequestOptions::ALLOW_REDIRECTS => true,
                    RequestOptions::HTTP_ERRORS => false,
                    RequestOptions::SYNCHRONOUS => true,
                ],
            )
            ->wait();
        assert($response instanceof ResponseInterface);

        /** @link https://docs.github.com/en/rest/releases/releases?apiVersion=2022-11-28#get-a-release-by-tag-name */
        if ($response->getStatusCode() === 404) {
            throw Exception\CouldNotFindReleaseAsset::forPackageWithMissingTag($package);
        }

        AssertHttp::responseStatusCode(200, $response);

        /** @var mixed $decodedRepsonse */
        $decodedRepsonse = json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR,
        );

        Assert::isArray($decodedRepsonse);
        Assert::keyExists($decodedRepsonse, 'assets');
        Assert::isList($decodedRepsonse['assets']);

        return array_map(
            static function (array $asset): array {
                Assert::keyExists($asset, 'name');
                Assert::stringNotEmpty($asset['name']);
                Assert::keyExists($asset, 'browser_download_url');
                Assert::stringNotEmpty($asset['browser_download_url']);

                return $asset;
            },
            $decodedRepsonse['assets'],
        );
    }
}
