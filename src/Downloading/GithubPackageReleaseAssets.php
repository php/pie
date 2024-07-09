<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Util\AuthHelper;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\Exception\CouldNotFindReleaseAsset;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPlatform;
use Psl\Json;
use Psl\Type;
use Psr\Http\Message\ResponseInterface;

use function assert;
use function in_array;
use function sprintf;
use function strtolower;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class GithubPackageReleaseAssets implements PackageReleaseAssets
{
    /** @psalm-api */
    public function __construct(
        private readonly AuthHelper $authHelper,
        private readonly ClientInterface $client,
        private readonly string $githubApiBaseUrl,
    ) {
    }

    /** @return non-empty-string */
    public function findWindowsDownloadUrlForPackage(TargetPlatform $targetPlatform, Package $package): string
    {
        $releaseAsset = $this->selectMatchingReleaseAsset(
            $targetPlatform,
            $package,
            $this->getReleaseAssetsForPackage($package),
        );

        return $releaseAsset['browser_download_url'];
    }

    /** @return non-empty-list<non-empty-string> */
    private function expectedWindowsAssetNames(TargetPlatform $targetPlatform, Package $package): array
    {
        if ($targetPlatform->operatingSystem !== OperatingSystem::Windows || $targetPlatform->windowsCompiler === null) {
            throw CouldNotFindReleaseAsset::forMissingWindowsCompiler($targetPlatform);
        }

        /**
         * During development, we swapped compiler/ts around. It is fairly trivial to support both, so we can check
         * both formats pretty easily, just to avoid confusion for package maintainers...
         */
        return [
            strtolower(sprintf(
                'php_%s-%s-%s-%s-%s-%s.zip',
                $package->extensionName->name(),
                $package->version,
                $targetPlatform->phpBinaryPath->majorMinorVersion(),
                $targetPlatform->threadSafety->asShort(),
                strtolower($targetPlatform->windowsCompiler->name),
                $targetPlatform->architecture->name,
            )),
            strtolower(sprintf(
                'php_%s-%s-%s-%s-%s-%s.zip',
                $package->extensionName->name(),
                $package->version,
                $targetPlatform->phpBinaryPath->majorMinorVersion(),
                strtolower($targetPlatform->windowsCompiler->name),
                $targetPlatform->threadSafety->asShort(),
                $targetPlatform->architecture->name,
            )),
        ];
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
    private function getReleaseAssetsForPackage(Package $package): array
    {
        // @todo confirm prettyName will always match the repo name - it might not
        $request = AddAuthenticationHeader::withAuthHeaderFromComposer(
            new Request('GET', $this->githubApiBaseUrl . '/repos/' . $package->name . '/releases/tags/' . $package->version),
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

        /** @link https://docs.github.com/en/rest/releases/releases?apiVersion=2022-11-28#get-a-release-by-tag-name */
        if ($response->getStatusCode() === 404) {
            throw Exception\CouldNotFindReleaseAsset::forPackageWithMissingTag($package);
        }

        AssertHttp::responseStatusCode(200, $response);

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
