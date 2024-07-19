<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Downloading\DownloadZip;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\WindowsExtensionAssetName;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function copy;
use function dirname;
use function file_exists;
use function implode;
use function is_file;
use function mkdir;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class WindowsInstall implements Install
{
    public function __invoke(DownloadedPackage $downloadedPackage, TargetPlatform $targetPlatform, OutputInterface $output): string
    {
        $extractedSourcePath = $downloadedPackage->extractedSourcePath;
        $sourceDllName       = $this->determineDllName($targetPlatform, $downloadedPackage);
        $extensionPath       = $targetPlatform->phpBinaryPath->extensionPath();
        $destinationDllName  = $extensionPath . DIRECTORY_SEPARATOR . 'php_' . $downloadedPackage->package->extensionName->name() . '.dll';

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

        $phpPath    = dirname($targetPlatform->phpBinaryPath->phpBinaryPath);
        $extrasPath = $phpPath
            . DIRECTORY_SEPARATOR . 'extras'
            . DIRECTORY_SEPARATOR . $downloadedPackage->package->extensionName->name();

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractedSourcePath)) as $file) {
            assert($file instanceof SplFileInfo);
            /**
             * Skip directories, the main DLL, PDB, and the downloaded.zip
             */
            if (
                $file->isDir()
                || str_replace('\\', '/', $file->getPathname()) === str_replace('\\', '/', $sourceDllName)
                || str_replace('\\', '/', $file->getPathname()) === str_replace('\\', '/', $sourcePdbName)
                || $file->getFilename() === DownloadZip::DOWNLOADED_ZIP_FILENAME
            ) {
                continue;
            }

            /**
             * Any other DLL file should be copied into the same path where `php.exe` is
             */
            if ($file->getExtension() === 'dll') {
                $destinationExtraDll = $phpPath . DIRECTORY_SEPARATOR . $file->getFilename();
                if (! copy($file->getPathname(), $destinationExtraDll) || ! file_exists($destinationExtraDll) && ! is_file($destinationExtraDll)) {
                    throw new RuntimeException('Failed to copy to ' . $destinationExtraDll);
                }

                $output->writeln('<info>Copied extra DLL:</info> ' . $destinationExtraDll);

                continue;
            }

            /**
             * Any other remaining file should be copied into the extras path, e.g. `C:\php\extras\my-php-ext\.`
             */
            $destinationPathname = $extrasPath . DIRECTORY_SEPARATOR . substr($file->getPathname(), strlen($extractedSourcePath) + 1);

            mkdir(dirname($destinationPathname), 0777, true);

            if (! copy($file->getPathname(), $destinationPathname) || ! file_exists($destinationPathname) && ! is_file($destinationPathname)) {
                throw new RuntimeException('Failed to copy to ' . $destinationPathname);
            }

            $output->writeln('<info>Copied extras:</info> ' . $destinationPathname);
        }

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
