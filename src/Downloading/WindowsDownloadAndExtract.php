<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Util\AuthHelper;
use GuzzleHttp\Psr7\Request;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Platform\TargetPlatform;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class WindowsDownloadAndExtract implements DownloadAndExtract
{
    /** @psalm-api */
    public function __construct(
        private readonly DownloadZip $downloadZip,
        private readonly ExtractZip $extractZip,
        private readonly AuthHelper $authHelper,
        private readonly PackageReleaseAssets $packageReleaseAssets,
    ) {
    }

    public function __invoke(TargetPlatform $targetPlatform, Package $package): DownloadedPackage
    {
        $windowsDownloadUrl = $this->packageReleaseAssets->findWindowsDownloadUrlForPackage($targetPlatform, $package);

        $localTempPath = Path::vaguelyRandomTempPath();

        $tmpZipFile = $this->downloadZip->downloadZipAndReturnLocalPath(
            AddAuthenticationHeader::withAuthHeaderFromComposer(
                new Request('GET', $windowsDownloadUrl),
                $package,
                $this->authHelper,
            ),
            $localTempPath,
        );

        $this->extractZip->to($tmpZipFile, $localTempPath);

        return DownloadedPackage::fromPackageAndExtractedPath($package, $localTempPath);
    }
}
