<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Util\AuthHelper;
use Composer\Util\HttpDownloader;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Platform\TargetPlatform;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface PackageReleaseAssets
{
    /** @return non-empty-string */
    public function findWindowsDownloadUrlForPackage(
        TargetPlatform $targetPlatform,
        Package $package,
        AuthHelper $authHelper,
        HttpDownloader $httpDownloader,
    ): string;
}
