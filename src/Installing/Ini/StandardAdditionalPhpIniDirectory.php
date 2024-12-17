<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\BinaryFile;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;

use function array_key_exists;
use function file_exists;
use function preg_match;
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
        return $this->extractIniDirectoryFromPhpInfo($targetPlatform->phpBinaryPath->phpinfo()) !== null;
    }

    public function setup(
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        BinaryFile $binaryFile,
        OutputInterface $output,
    ): bool {
        $phpinfo = $targetPlatform->phpBinaryPath->phpinfo();

        $additionalIniFilesPath = $this->extractIniDirectoryFromPhpInfo($phpinfo);
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
        );

        if (! $addingExtensionWasSuccessful && $pieCreatedTheIniFile) {
            unlink($expectedIniFile);
        }

        return $addingExtensionWasSuccessful;
    }

    private function extractIniDirectoryFromPhpInfo(string $phpinfoString): string|null
    {
        if (
            preg_match('/Scan this dir for additional \.ini files([ =>\t]*)(.*)/', $phpinfoString, $m)
            && array_key_exists(2, $m)
            && $m[2] !== ''
            && $m[2] !== '(none)'
        ) {
            return $m[2];
        }

        return null;
    }
}
