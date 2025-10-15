<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Composer\IO\IOInterface;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\File\BinaryFile;
use Php\Pie\Platform\TargetPlatform;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface Install
{
    /**
     * Install the extension in the given target platform's PHP, and return the location of the installed shared object
     * or DLL, depending on the platform implementation.
     */
    public function __invoke(
        DownloadedPackage $downloadedPackage,
        TargetPlatform $targetPlatform,
        IOInterface $io,
        bool $attemptToSetupIniFile,
    ): BinaryFile;
}
