<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Php\Pie\DependencyResolver\Package;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface PackageReleaseAssets
{
    /** @return non-empty-string */
    public function findWindowsDownloadUrlForPackage(Package $package): string;
}
