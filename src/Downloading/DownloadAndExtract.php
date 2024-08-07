<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\Platform\TargetPlatform;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface DownloadAndExtract
{
    public function __invoke(TargetPlatform $targetPlatform, Package $package): DownloadedPackage;
}
