<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Php\Pie\DependencyResolver\Package;

interface DownloadAndExtract
{
    public function __invoke(Package $package): DownloadedPackage;
}
