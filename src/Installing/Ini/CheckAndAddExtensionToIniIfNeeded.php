<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Composer\IO\IOInterface;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;
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

    /**
     * @param non-empty-string     $iniFile
     * @param callable():bool|null $additionalEnableStep
     */
    public function __invoke(
        string $iniFile,
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        IOInterface $io,
        callable|null $additionalEnableStep,
    ): bool {
        if (! file_exists($iniFile) || ! is_readable($iniFile)) {
            $io->write(
                sprintf(
                    'PHP is configured to use %s, but it did not exist, or is not readable by PIE.',
                    $iniFile,
                ),
                verbosity: IOInterface::VERBOSE,
            );

            return false;
        }

        if (($this->isExtensionAlreadyInTheIniFile)($iniFile, $downloadedPackage->package->extensionName())) {
            $io->write(
                sprintf(
                    'Extension is already enabled in the INI file %s',
                    $iniFile,
                ),
                verbosity: IOInterface::VERBOSE,
            );

            if ($additionalEnableStep !== null && ! $additionalEnableStep()) {
                return false;
            }

            try {
                $targetPlatform->phpBinaryPath->assertExtensionIsLoadedInRuntime($downloadedPackage->package->extensionName(), $io);

                return true;
            } catch (Throwable $anything) {
                $io->write(sprintf(
                    '<error>Something went wrong verifying the %s extension is enabled: %s</error>',
                    $downloadedPackage->package->extensionName()->name(),
                    $anything->getMessage(),
                ));

                return false;
            }
        }

        return ($this->addExtensionToTheIniFile)(
            $iniFile,
            $downloadedPackage->package,
            $targetPlatform->phpBinaryPath,
            $io,
            $additionalEnableStep,
        );
    }
}
