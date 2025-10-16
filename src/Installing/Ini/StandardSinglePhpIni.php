<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Composer\IO\IOInterface;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\File\BinaryFile;
use Php\Pie\Platform\TargetPlatform;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class StandardSinglePhpIni implements SetupIniApproach
{
    public function __construct(
        private readonly CheckAndAddExtensionToIniIfNeeded $checkAndAddExtensionToIniIfNeeded,
    ) {
    }

    public function canBeUsed(TargetPlatform $targetPlatform): bool
    {
        return $targetPlatform->phpBinaryPath->loadedIniConfigurationFile() !== null;
    }

    public function setup(
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        BinaryFile $binaryFile,
        IOInterface $io,
    ): bool {
        $ini = $targetPlatform->phpBinaryPath->loadedIniConfigurationFile();

        /** In practice, this shouldn't happen since {@see canBeUsed()} checks this */
        if ($ini === null) {
            return false;
        }

        return ($this->checkAndAddExtensionToIniIfNeeded)(
            $ini,
            $targetPlatform,
            $downloadedPackage,
            $io,
            null,
        );
    }
}
