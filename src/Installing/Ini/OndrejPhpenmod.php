<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Composer\IO\IOInterface;
use Composer\Util\Platform;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\File\BinaryFile;
use Php\Pie\File\Sudo;
use Php\Pie\File\SudoCreate;
use Php\Pie\File\SudoUnlink;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function array_unshift;
use function file_exists;
use function is_dir;
use function is_writable;
use function preg_match;
use function rtrim;
use function sprintf;

use const DIRECTORY_SEPARATOR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class OndrejPhpenmod implements SetupIniApproach
{
    private const DEFAULT_PHPENMOD            = 'phpenmod';
    private const DEFAULT_MODS_AVAILABLE_PATH = '/etc/php/%s/mods-available';

    public function __construct(
        private readonly CheckAndAddExtensionToIniIfNeeded $checkAndAddExtensionToIniIfNeeded,
        private readonly string $phpenmod = self::DEFAULT_PHPENMOD,
        private readonly string $modsAvailablePath = self::DEFAULT_MODS_AVAILABLE_PATH,
    ) {
    }

    public function canBeUsed(TargetPlatform $targetPlatform): bool
    {
        return $this->phpenmodPath() !== null;
    }

    public function setup(
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        BinaryFile $binaryFile,
        IOInterface $io,
    ): bool {
        $phpenmodPath = $this->phpenmodPath();

        /** In practice, this shouldn't happen since {@see canBeUsed()} checks this */
        if ($phpenmodPath === null) {
            return false;
        }

        // the Ondrej repo uses an additional php.ini directory, if this isn't set, we may not actually be using Ondrej repo for this particular PHP install
        $additionalPhpIniPath = $targetPlatform->phpBinaryPath->additionalIniDirectory();

        if ($additionalPhpIniPath === null) {
            $io->write(
                'Additional INI file path was not set - may not be Ondrej PHP repo',
                verbosity: IOInterface::VERBOSE,
            );

            return false;
        }

        // Cursory check for the expected PHP INI directory; this is another indication we're using the Ondrej repo
        if (preg_match('#/etc/php/\d\.\d/[a-z-_]+/conf.d#', $additionalPhpIniPath) !== 1) {
            $io->write(
                sprintf(
                    'Warning: additional INI file path was not in the expected format (/etc/php/{version}/{sapi}/conf.d). Path was: %s',
                    $additionalPhpIniPath,
                ),
                verbosity: IOInterface::VERY_VERBOSE,
            );
        }

        $expectedModsAvailablePath = sprintf($this->modsAvailablePath, $targetPlatform->phpBinaryPath->majorMinorVersion());

        if (! file_exists($expectedModsAvailablePath)) {
            $io->write(
                sprintf(
                    'Mods available path %s does not exist',
                    $expectedModsAvailablePath,
                ),
                verbosity: IOInterface::VERBOSE,
            );

            return false;
        }

        if (! is_dir($expectedModsAvailablePath)) {
            $io->write(
                sprintf(
                    'Mods available path %s is not a directory',
                    $expectedModsAvailablePath,
                ),
                verbosity: IOInterface::VERBOSE,
            );

            return false;
        }

        $needSudo = false;
        if (! is_writable($expectedModsAvailablePath)) {
            if (! Sudo::exists()) {
                $io->write(
                    sprintf(
                        'Mods available path %s is not writable',
                        $expectedModsAvailablePath,
                    ),
                    verbosity: IOInterface::VERBOSE,
                );

                return false;
            }

            $needSudo = true;
        }

        $expectedIniFile = sprintf(
            '%s%s%s.ini',
            rtrim($expectedModsAvailablePath, DIRECTORY_SEPARATOR),
            DIRECTORY_SEPARATOR,
            $downloadedPackage->package->extensionName()->name(),
        );

        $pieCreatedTheIniFile = false;
        if (! file_exists($expectedIniFile)) {
            $io->write(
                sprintf(
                    'Creating new INI file based on extension priority: %s',
                    $expectedIniFile,
                ),
                verbosity: IOInterface::VERY_VERBOSE,
            );
            $pieCreatedTheIniFile = true;
            SudoCreate::file($expectedIniFile);
        }

        $addingExtensionWasSuccessful = ($this->checkAndAddExtensionToIniIfNeeded)(
            $expectedIniFile,
            $targetPlatform,
            $downloadedPackage,
            $io,
            static function () use ($needSudo, $phpenmodPath, $targetPlatform, $downloadedPackage, $io): bool {
                try {
                    $processArgs = [
                        $phpenmodPath,
                        '-v',
                        $targetPlatform->phpBinaryPath->majorMinorVersion(),
                        '-s',
                        'ALL',
                        $downloadedPackage->package->extensionName()->name(),
                    ];

                    if ($needSudo && Sudo::exists()) {
                        $io->write(
                            'Using sudo to elevate privileges for phpenmod',
                            verbosity: IOInterface::VERBOSE,
                        );
                        array_unshift($processArgs, Sudo::find());
                    }

                    Process::run($processArgs);

                    return true;
                } catch (ProcessFailedException $processFailedException) {
                    $io->write(
                        sprintf(
                            'Failed to use %s to enable %s for PHP %s: %s',
                            $phpenmodPath,
                            $downloadedPackage->package->extensionName()->name(),
                            $targetPlatform->phpBinaryPath->majorMinorVersion(),
                            $processFailedException->getMessage(),
                        ),
                        verbosity: IOInterface::VERBOSE,
                    );

                    return false;
                }
            },
        );

        if (! $addingExtensionWasSuccessful && $pieCreatedTheIniFile) {
            SudoUnlink::singleFile($expectedIniFile);
        }

        return $addingExtensionWasSuccessful;
    }

    /** @return non-empty-string|null */
    private function phpenmodPath(): string|null
    {
        if (Platform::isWindows()) {
            return null;
        }

        try {
            $phpenmodPath = Process::run(['which', $this->phpenmod]);

            return $phpenmodPath !== '' ? $phpenmodPath : null;
        } catch (ProcessFailedException) {
            return null;
        }
    }
}
