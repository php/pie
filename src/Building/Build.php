<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use Composer\IO\IOInterface;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\File\BinaryFile;
use Php\Pie\Platform\TargetPhp\PhpizePath;
use Php\Pie\Platform\TargetPlatform;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface Build
{
    /** @param list<non-empty-string> $configureOptions */
    public function __invoke(
        DownloadedPackage $downloadedPackage,
        TargetPlatform $targetPlatform,
        array $configureOptions,
        IOInterface $io,
        PhpizePath|null $phpizePath,
    ): BinaryFile;
}
