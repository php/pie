<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function file_exists;
use function is_readable;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class CheckAndAddExtensionToIniIfNeeded
{
    public function __construct(
        private readonly IsExtensionAlreadyInTheIniFile $isExtensionAlreadyInTheIniFile,
        private readonly AddExtensionToTheIniFile $addExtensionToTheIniFile,
    ) {
    }

    /** @param non-empty-string $iniFile */
    public function __invoke(
        string $iniFile,
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        OutputInterface $output,
    ): bool {
        if (! file_exists($iniFile) || ! is_readable($iniFile)) {
            $output->writeln(
                sprintf(
                    'PHP is configured to use %s, but it did not exist, or is not readable by PIE.',
                    $iniFile,
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );

            return false;
        }

        if (($this->isExtensionAlreadyInTheIniFile)($iniFile, $downloadedPackage->package->extensionName)) {
            $output->writeln(
                sprintf(
                    'Extension is already enabled in the INI file %s',
                    $iniFile,
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

        return ($this->addExtensionToTheIniFile)($iniFile, $downloadedPackage->package, $targetPlatform->phpBinaryPath, $output);
    }
}
