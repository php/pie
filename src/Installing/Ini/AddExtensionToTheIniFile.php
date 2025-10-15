<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Composer\IO\IOInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionType;
use Php\Pie\File\Sudo;
use Php\Pie\File\SudoFilePut;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Throwable;

use function file_get_contents;
use function is_readable;
use function is_string;
use function is_writable;
use function sprintf;

use const PHP_EOL;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class AddExtensionToTheIniFile
{
    /** @param callable():bool|null $additionalEnableStep */
    public function __invoke(
        string $ini,
        Package $package,
        PhpBinaryPath $phpBinaryPath,
        IOInterface $io,
        callable|null $additionalEnableStep,
    ): bool {
        if (! is_writable($ini) && ! Sudo::exists()) {
            $io->write(
                sprintf(
                    'PHP is configured to use %s, but it is not writable by PIE.',
                    $ini,
                ),
                verbosity: IOInterface::VERBOSE,
            );

            return false;
        }

        if (! is_readable($ini)) {
            $io->write(
                sprintf(
                    'Could not read %s to make a backup of it, aborting enablement of extension',
                    $ini,
                ),
                verbosity: IOInterface::VERBOSE,
            );

            return false;
        }

        $originalIniContent = file_get_contents($ini);

        if (! is_string($originalIniContent)) {
            $io->write(
                sprintf(
                    'Tried making a backup of %s but could not read it, aborting enablement of extension',
                    $ini,
                ),
                verbosity: IOInterface::VERBOSE,
            );

            return false;
        }

        try {
            SudoFilePut::contents(
                $ini,
                $originalIniContent . $this->iniFileContent($package),
            );
            $io->write(
                sprintf(
                    'Enabled extension %s in the INI file %s',
                    $package->extensionName()->name(),
                    $ini,
                ),
                verbosity: IOInterface::VERBOSE,
            );

            if ($additionalEnableStep !== null && ! $additionalEnableStep()) {
                return false;
            }

            $phpBinaryPath->assertExtensionIsLoadedInRuntime($package->extensionName(), $io);

            return true;
        } catch (Throwable $anything) {
            SudoFilePut::contents($ini, $originalIniContent);

            $io->write(sprintf(
                '<error>Something went wrong enabling the %s extension: %s</error>',
                $package->extensionName()->name(),
                $anything->getMessage(),
            ));

            return false;
        }
    }

    /** @return non-empty-string */
    private function iniFileContent(Package $package): string
    {
        return PHP_EOL
            . '; PIE automatically added this to enable the ' . $package->name() . ' extension' . PHP_EOL
            . '; priority=' . $package->priority() . PHP_EOL
            . ($package->extensionType() === ExtensionType::PhpModule ? 'extension' : 'zend_extension')
            . '='
            . $package->extensionName()->name() . PHP_EOL;
    }
}
