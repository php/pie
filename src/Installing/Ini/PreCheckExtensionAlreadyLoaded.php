<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Composer\IO\IOInterface;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\File\BinaryFile;
use Php\Pie\Platform\TargetPhp\Exception\ExtensionIsNotLoaded;
use Php\Pie\Platform\TargetPlatform;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class PreCheckExtensionAlreadyLoaded implements SetupIniApproach
{
    public function canBeUsed(TargetPlatform $targetPlatform): bool
    {
        return true;
    }

    public function setup(
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        BinaryFile $binaryFile,
        IOInterface $io,
    ): bool {
        try {
            $targetPlatform->phpBinaryPath->assertExtensionIsLoadedInRuntime(
                $downloadedPackage->package->extensionName(),
                $io,
            );

            return true;
        } catch (ExtensionIsNotLoaded) {
            return false;
        }
    }
}
