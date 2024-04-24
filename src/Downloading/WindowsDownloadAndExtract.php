<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Util\AuthHelper;
use GuzzleHttp\Psr7\Request;
use Php\Pie\DependencyResolver\Package;

use function file_exists;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

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

        // @todo extract to a static util
        $localTempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_downloader_', true);
        if (! file_exists($localTempPath)) {
            mkdir($localTempPath, recursive: true);
        }

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
