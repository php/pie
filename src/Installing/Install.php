<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface Install
{
    public function __invoke(DownloadedPackage $downloadedPackage, TargetPlatform $targetPlatform, OutputInterface $output): void;
}
