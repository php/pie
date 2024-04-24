<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Util\AuthHelper;
use GuzzleHttp\Psr7\Request;
use Php\Pie\DependencyResolver\Package;

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

    public function __invoke(Package $package): DownloadedPackage
    {
        $windowsDownloadUrl = $this->packageReleaseAssets->findWindowsDownloadUrlForPackage($package);

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
