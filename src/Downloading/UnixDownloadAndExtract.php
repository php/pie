<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Php\Pie\DependencyResolver\Package;

use function file_exists;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class UnixDownloadAndExtract implements DownloadAndExtract
{
    public function __construct(
        private readonly DownloadZip $downloadZip,
        private readonly ExtractZip $extractZip,
    ) {
    }

    public function __invoke(Package $package): DownloadedPackage
    {
        $localTempPath = sys_get_temp_dir() . '/' . uniqid('pie_downloader_', true);
        if (! file_exists($localTempPath)) {
            mkdir($localTempPath, recursive: true);
        }

        $tmpZipFile = $this->downloadZip->downloadZipAndReturnLocalPath($package, $localTempPath);

        $extractedPath = $this->extractZip->to($tmpZipFile, $localTempPath);

        return DownloadedPackage::fromPackageAndExtractedPath($package, $extractedPath);
    }
}
