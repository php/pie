<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function file_get_contents;
use function file_put_contents;
use function is_string;
use function is_writable;
use function sprintf;

use const PHP_EOL;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class AddExtensionToTheIniFile
{
    public function __invoke(string $ini, Package $package, PhpBinaryPath $phpBinaryPath, OutputInterface $output): bool
    {
        if (! is_writable($ini)) {
            $output->writeln(
                sprintf(
                    'PHP is configured to use %s, but it is not writable by PIE.',
                    $ini,
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );

            return false;
        }

        $originalIniContent = file_get_contents($ini);

        if (! is_string($originalIniContent)) {
            $output->writeln(
                sprintf(
                    'Tried making a backup of %s but could not read it, aborting enablement of extension',
                    $ini,
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );

            return false;
        }

        try {
            file_put_contents(
                $ini,
                $originalIniContent . PHP_EOL
                . '; PIE automatically added this to enable the ' . $package->name . ' extension' . PHP_EOL
                . ($package->extensionType === ExtensionType::PhpModule ? 'extension' : 'zend_extension')
                . '='
                . $package->extensionName->name() . PHP_EOL,
            );
            $output->writeln(
                sprintf(
                    'Enabled extension %s in the INI file %s',
                    $package->extensionName->name(),
                    $ini,
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );

            $phpBinaryPath->assertExtensionIsLoadedInRuntime($package->extensionName, $output);

            return true;
        } catch (Throwable $anything) {
            file_put_contents($ini, $originalIniContent);

            $output->writeln(sprintf(
                '<error>Something went wrong enabling the %s extension: %s</error>',
                $package->extensionName->name(),
                $anything->getMessage(),
            ));

            return false;
        }
    }
}
