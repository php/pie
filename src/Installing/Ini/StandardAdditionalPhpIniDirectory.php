<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\BinaryFile;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;

use function file_exists;
use function rtrim;
use function sprintf;
use function touch;
use function unlink;

use const DIRECTORY_SEPARATOR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class StandardAdditionalPhpIniDirectory implements SetupIniApproach
{
    public function __construct(
        private readonly CheckAndAddExtensionToIniIfNeeded $checkAndAddExtensionToIniIfNeeded,
    ) {
    }

    public function canBeUsed(TargetPlatform $targetPlatform): bool
    {
        return $targetPlatform->phpBinaryPath->additionalIniDirectory() !== null;
    }

    public function setup(
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        BinaryFile $binaryFile,
        OutputInterface $output,
    ): bool {
        $additionalIniFilesPath = $targetPlatform->phpBinaryPath->additionalIniDirectory();

        /** In practice, this shouldn't happen since {@see canBeUsed()} checks this */
        if ($additionalIniFilesPath === null) {
            return false;
        }

        $expectedIniFile = sprintf(
            '%s%s%d-%s.ini',
            rtrim($additionalIniFilesPath, DIRECTORY_SEPARATOR),
            DIRECTORY_SEPARATOR,
            $downloadedPackage->package->priority,
            $downloadedPackage->package->extensionName->name(),
        );

        $pieCreatedTheIniFile = false;
        if (! file_exists($expectedIniFile)) {
            $output->writeln(
                sprintf(
                    'Creating new INI file based on extension priority: %s',
                    $expectedIniFile,
                ),
                OutputInterface::VERBOSITY_VERY_VERBOSE,
            );
            $pieCreatedTheIniFile = true;
            touch($expectedIniFile);
        }

        $addingExtensionWasSuccessful = ($this->checkAndAddExtensionToIniIfNeeded)(
            $expectedIniFile,
            $targetPlatform,
            $downloadedPackage,
            $output,
            null,
        );

        if (! $addingExtensionWasSuccessful && $pieCreatedTheIniFile) {
            unlink($expectedIniFile);
        }

        return $addingExtensionWasSuccessful;
    }
}
