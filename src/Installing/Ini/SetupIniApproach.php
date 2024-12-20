<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\BinaryFile;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface SetupIniApproach
{
    /**
     * Preliminary indication on whether this approach can be used on the
     * target platform (returns `true`) or not (returns `false`).
     */
    public function canBeUsed(TargetPlatform $targetPlatform): bool;

    /**
     * Should return true if the INI approach successfully set up the extension,
     * or false otherwise.
     */
    public function setup(
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        BinaryFile $binaryFile,
        OutputInterface $output,
    ): bool;
}
