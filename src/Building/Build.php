<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use Php\Pie\Downloading\DownloadedPackage;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface Build
{
    public function __invoke(DownloadedPackage $downloadedPackage, OutputInterface $output): void;
}
