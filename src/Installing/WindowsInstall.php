<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\WindowsExtensionAssetName;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

use function copy;
use function file_exists;
use function implode;
use function is_file;
use function sprintf;
use function str_replace;

use const DIRECTORY_SEPARATOR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class WindowsInstall implements Install
{
    public function __invoke(DownloadedPackage $downloadedPackage, TargetPlatform $targetPlatform, OutputInterface $output): string
    {
        $sourceDllName      = $this->determineDllName($targetPlatform, $downloadedPackage);
        $extensionPath      = $targetPlatform->phpBinaryPath->extensionPath();
        $destinationDllName = $extensionPath . DIRECTORY_SEPARATOR . 'php_' . $downloadedPackage->package->extensionName->name() . '.dll';

        if (! copy($sourceDllName, $destinationDllName) || ! file_exists($destinationDllName) && ! is_file($destinationDllName)) {
            throw new RuntimeException('Failed to install DLL to ' . $destinationDllName);
        }

        $output->writeln('<info>Copied DLL to:</info> ' . $destinationDllName);

        $sourcePdbName = str_replace('.dll', '.pdb', $sourceDllName);
        if (file_exists($sourcePdbName)) {
            $destinationPdbName = str_replace('.dll', '.pdb', $destinationDllName);

            if (! copy($sourcePdbName, $destinationPdbName) || ! file_exists($destinationPdbName) && ! is_file($destinationPdbName)) {
                throw new RuntimeException('Failed to install PDB to ' . $destinationPdbName);
            }

            $output->writeln('<info>Copied PDB to:</info> ' . $destinationPdbName);
        }

        // @todo copy any OTHER .dll file next to `C:\path\to\php\php.exe`
        // @todo copy any other file (excluding those above, and `downloaded.zip`) to `C:\path\to\php\extras\{extension-name}\.`

        /**
         * @link https://github.com/php/pie/issues/20
         *
         * @todo this should be improved in future to try to automatically set up the ext
         */
        $output->writeln(sprintf(
            '<comment>You must now add "%s=%s" to your php.ini</comment>',
            $downloadedPackage->package->extensionType === ExtensionType::PhpModule ? 'extension' : 'zend_extension',
            $downloadedPackage->package->extensionName->name(),
        ));

        return $destinationDllName;
    }

    /** @return non-empty-string */
    private function determineDllName(TargetPlatform $targetPlatform, DownloadedPackage $package): string
    {
        $possibleDllNames = WindowsExtensionAssetName::dllNames($targetPlatform, $package->package);
        foreach ($possibleDllNames as $dllName) {
            $fullDllName = $package->extractedSourcePath . '/' . $dllName;
            if (file_exists($fullDllName)) {
                return $fullDllName;
            }
        }

        throw new RuntimeException('Unable to find DLL for package, checked: ' . implode(', ', $possibleDllNames));
    }
}
