<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\BinaryFile;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPhp\Exception\ExtensionIsNotLoaded;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;

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
        OutputInterface $output,
    ): bool {
        try {
            $targetPlatform->phpBinaryPath->assertExtensionIsLoadedInRuntime(
                $downloadedPackage->package->extensionName,
                $output,
            );

            return true;
        } catch (ExtensionIsNotLoaded) {
            return false;
        }
    }
}
