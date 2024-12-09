<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\BinaryFile;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_key_exists;
use function file_exists;
use function is_readable;
use function preg_match;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class StandardSinglePhpIni implements SetupIniApproach
{
    public function __construct(
        private readonly IsExtensionAlreadyInTheIniFile $isExtensionAlreadyInTheIniFile,
        private readonly AddExtensionToTheIniFile $addExtensionToTheIniFile,
    ) {
    }

    public function canBeUsed(TargetPlatform $targetPlatform): bool
    {
        return $this->extractPhpIniFromPhpInfo($targetPlatform->phpBinaryPath->phpinfo()) !== null;
    }

    public function setup(
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        BinaryFile $binaryFile,
        OutputInterface $output,
    ): bool {
        $ini = $this->extractPhpIniFromPhpInfo($targetPlatform->phpBinaryPath->phpinfo());

        /** In practice, this shouldn't happen since {@see canBeUsed()} checks this */
        if ($ini === null) {
            return false;
        }

        if (! file_exists($ini) || ! is_readable($ini)) {
            $output->writeln(
                sprintf(
                    'PHP is configured to use %s, but it did not exist, or is not readable by PIE.',
                    $ini,
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );

            return false;
        }

        if (($this->isExtensionAlreadyInTheIniFile)($ini, $downloadedPackage->package->extensionName)) {
            $output->writeln(
                sprintf(
                    'Extension is already enabled in the INI file %s',
                    $ini,
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );

            try {
                $targetPlatform->phpBinaryPath->assertExtensionIsLoadedInRuntime($downloadedPackage->package->extensionName, $output);

                return true;
            } catch (Throwable $anything) {
                $output->writeln(sprintf(
                    '<error>Something went wrong verifying the %s extension is enabled: %s</error>',
                    $downloadedPackage->package->extensionName->name(),
                    $anything->getMessage(),
                ));

                return false;
            }
        }

        return ($this->addExtensionToTheIniFile)($ini, $downloadedPackage->package, $targetPlatform->phpBinaryPath, $output);
    }

    private function extractPhpIniFromPhpInfo(string $phpinfoString): string|null
    {
        if (
            preg_match('/Loaded Configuration File([ =>\t]*)(.*)/', $phpinfoString, $m)
            && array_key_exists(2, $m)
            && $m[2] !== ''
            && $m[2] !== '(none)'
        ) {
            return $m[2];
        }

        return null;
    }
}
