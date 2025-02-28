<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\File\BinaryFile;
use Php\Pie\File\Sudo;
use Php\Pie\File\SudoFilePut;
use Php\Pie\File\SudoUnlink;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;

use function file_exists;
use function is_writable;
use function rtrim;
use function sprintf;

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

        if (! file_exists($additionalIniFilesPath)) {
            $output->writeln(
                sprintf(
                    'PHP is configured to use additional INI file path %s, but it did not exist.',
                    $additionalIniFilesPath,
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );

            return false;
        }

        if (! is_writable($additionalIniFilesPath) && ! Sudo::exists()) {
            $output->writeln(
                sprintf(
                    'PHP is configured to use additional INI file path %s, but it was not writable by PIE.',
                    $additionalIniFilesPath,
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );

            return false;
        }

        $expectedIniFile = sprintf(
            '%s%s%d-%s.ini',
            rtrim($additionalIniFilesPath, DIRECTORY_SEPARATOR),
            DIRECTORY_SEPARATOR,
            $downloadedPackage->package->priority(),
            $downloadedPackage->package->extensionName()->name(),
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
            SudoFilePut::contents($expectedIniFile, '');
        }

        $addingExtensionWasSuccessful = ($this->checkAndAddExtensionToIniIfNeeded)(
            $expectedIniFile,
            $targetPlatform,
            $downloadedPackage,
            $output,
            null,
        );

        if (! $addingExtensionWasSuccessful && $pieCreatedTheIniFile) {
            SudoUnlink::singleFile($expectedIniFile);
        }

        return $addingExtensionWasSuccessful;
    }
}
