<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Util\AuthHelper;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Php\Pie\DependencyResolver\Package;
use Psl\Json;
use Psl\Type;
use Psr\Http\Message\ResponseInterface;

use function assert;
use function sprintf;
use function str_replace;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class GithubPackageReleaseAssets implements PackageReleaseAssets
{
    /** @psalm-api */
    public function __construct(
        private readonly AuthHelper $authHelper,
        private readonly ClientInterface $client,
    ) {
    }

    /** @return non-empty-string */
    public function findWindowsDownloadUrlForPackage(Package $package): string
    {
        $releaseAsset = $this->selectMatchingReleaseAsset(
            $package,
            $this->getReleaseAssetsForPackage($package),
        );

        return $releaseAsset['browser_download_url'];
    }

    /** @return non-empty-string */
    private function expectedWindowsAssetName(Package $package): string
    {
        // @todo source these from the right places...
        $arch          = 'x86';
        $ts            = 'nts';
        $compiler      = 'vs16';
        $phpVersion    = '8.3';
        $extensionName = str_replace('-', '_', 'example-pie-extension');

        return sprintf(
            'php_%s-%s-%s-%s-%s-%s.zip',
            $extensionName,
            $package->version,
            $phpVersion,
            $compiler,
            $ts,
            $arch,
        );
    }

    /** @link https://github.com/squizlabs/PHP_CodeSniffer/issues/3734 */
    // phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName
    /**
     * @param list<array{name: non-empty-string, browser_download_url: non-empty-string, ...}> $releaseAssets
     *
     * @return array{name: non-empty-string, browser_download_url: non-empty-string, ...}
     */
    // phpcs:enable
    private function selectMatchingReleaseAsset(Package $package, array $releaseAssets): array
    {
        $expectedAssetName = $this->expectedWindowsAssetName($package);

        foreach ($releaseAssets as $releaseAsset) {
            if ($releaseAsset['name'] === $expectedAssetName) {
                return $releaseAsset;
            }
        }

        throw Exception\CouldNotFindReleaseAsset::forPackage($package, $expectedAssetName);
    }

    /** @return list<array{name: non-empty-string, browser_download_url: non-empty-string, ...}> */
    private function getReleaseAssetsForPackage(Package $package): array
    {
        // @todo dynamic URL, don't hard code it...
        // @todo confirm prettyName will always match the repo name - it might not
        $request = AddAuthenticationHeader::withAuthHeaderFromComposer(
            new Request('GET', 'https://api.github.com/repos/' . $package->name . '/releases/tags/' . $package->version),
            $package,
            $this->authHelper,
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

        // @todo check response was successful

        $releaseAssets = Json\typed(
            (string) $response->getBody(),
            Type\shape(
                [
                    'assets' => Type\vec(Type\shape(
                        [
                            'name' => Type\non_empty_string(),
                            'browser_download_url' => Type\non_empty_string(),
                        ],
                        true,
                    )),
                ],
                true,
            ),
        );

        return $releaseAssets['assets'];
    }
}
