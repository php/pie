<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\BinaryFile;
use Php\Pie\Downloading\DownloadedPackage;
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
    public function __construct(private readonly SetupIniFile $setupIniFile)
    {
    }

    public function __invoke(
        DownloadedPackage $downloadedPackage,
        TargetPlatform $targetPlatform,
        OutputInterface $output,
        bool $attemptToSetupIniFile,
    ): BinaryFile {
        $extractedSourcePath = $downloadedPackage->extractedSourcePath;
        $sourceDllName       = WindowsExtensionAssetName::determineDllName($targetPlatform, $downloadedPackage);
        $sourcePdbName       = str_replace('.dll', '.pdb', $sourceDllName);
        assert($sourcePdbName !== '');

        $destinationDllName = $this->copyExtensionDll($targetPlatform, $downloadedPackage, $sourceDllName);
        $output->writeln('<info>Copied DLL to:</info> ' . $destinationDllName);

        $destinationPdbName = $this->copyExtensionPdb($targetPlatform, $downloadedPackage, $sourcePdbName, $destinationDllName);
        if ($destinationPdbName !== null) {
            $output->writeln('<info>Copied PDB to:</info> ' . $destinationPdbName);
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractedSourcePath)) as $file) {
            assert($file instanceof SplFileInfo);

            /**
             * Skip directories, the main DLL, PDB
             */
            if (
                $file->isDir()
                || $this->normalisedPathsMatch($file->getPathname(), $sourceDllName)
                || $this->normalisedPathsMatch($file->getPathname(), $sourcePdbName)
            ) {
                continue;
            }

            $destinationExtraDll = $this->copyDependencyDll($targetPlatform, $file);
            if ($destinationExtraDll !== null) {
                $output->writeln('<info>Copied extra DLL:</info> ' . $destinationExtraDll);

                continue;
            }

            $destinationPathname = $this->copyExtraFile($targetPlatform, $downloadedPackage, $file);
            $output->writeln('<info>Copied extras:</info> ' . $destinationPathname);
        }

        /**
         * @link https://github.com/php/pie/issues/20
         *
         * @todo this should be improved in future to try to automatically set up the ext
         */
        $output->writeln(sprintf(
            '<comment>You must now add "%s=%s" to your php.ini</comment>',
            $downloadedPackage->package->extensionType() === ExtensionType::PhpModule ? 'extension' : 'zend_extension',
            $downloadedPackage->package->extensionName()->name(),
        ));

        $binaryFile = BinaryFile::fromFileWithSha256Checksum($destinationDllName);

        ($this->setupIniFile)(
            $targetPlatform,
            $downloadedPackage,
            $binaryFile,
            $output,
            $attemptToSetupIniFile,
        );

        return $binaryFile;
    }

    /**
     * Normalise both path parameters (i.e. replace `\` with `/`) and compare them. Useful if the two paths are built
     * differently with different/incorrect directory separators, e.g. "C:\path\to/thing" vs "C:\path\to\thing"
     */
    private function normalisedPathsMatch(string $first, string $second): bool
    {
        return str_replace('\\', '/', $first) === str_replace('\\', '/', $second);
    }

    /**
     * Copy the main PHP extension DLL into the extension path.
     *
     * @param non-empty-string $sourceDllName
     *
     * @return non-empty-string
     */
    private function copyExtensionDll(TargetPlatform $targetPlatform, DownloadedPackage $downloadedPackage, string $sourceDllName): string
    {
        $destinationDllName = $targetPlatform->phpBinaryPath->extensionPath() . DIRECTORY_SEPARATOR
            . 'php_' . $downloadedPackage->package->extensionName()->name() . '.dll';

        if (! copy($sourceDllName, $destinationDllName) || ! file_exists($destinationDllName) && ! is_file($destinationDllName)) {
            throw new RuntimeException('Failed to install DLL to ' . $destinationDllName);
        }

        return $destinationDllName;
    }

    /**
     * Copy the PDB (Program Database, which is debugging information basically), into the same directory as the DLL,
     * if it exists.
     *
     * Returns `null` if the source PDB does not exist (and thus, does not need to be copied).
     *
     * @param non-empty-string $sourcePdbName
     * @param non-empty-string $destinationDllName
     *
     * @return non-empty-string|null
     */
    private function copyExtensionPdb(TargetPlatform $targetPlatform, DownloadedPackage $downloadedPackage, string $sourcePdbName, string $destinationDllName): string|null
    {
        if (! file_exists($sourcePdbName)) {
            return null;
        }

        $destinationPdbName = str_replace('.dll', '.pdb', $destinationDllName);
        assert($destinationPdbName !== '');

        if (! copy($sourcePdbName, $destinationPdbName) || ! file_exists($destinationPdbName) && ! is_file($destinationPdbName)) {
            throw new RuntimeException('Failed to install PDB to ' . $destinationPdbName);
        }

        return $destinationPdbName;
    }

    /**
     * Any other DLL file included in the source package should be copied into the same path where `php.exe` is - these
     * would commonly be dependencies/libraries that the extension depends on, and is bundled with.
     *
     * If the file is NOT a DLL, this method will return `null`
     *
     * @return non-empty-string|null
     */
    private function copyDependencyDll(TargetPlatform $targetPlatform, SplFileInfo $file): string|null
    {
        if ($file->getExtension() !== 'dll') {
            return null;
        }

        $destinationExtraDll = dirname($targetPlatform->phpBinaryPath->phpBinaryPath) . DIRECTORY_SEPARATOR . $file->getFilename();

        if (! copy($file->getPathname(), $destinationExtraDll) || ! file_exists($destinationExtraDll) && ! is_file($destinationExtraDll)) {
            throw new RuntimeException('Failed to copy to ' . $destinationExtraDll);
        }

        return $destinationExtraDll;
    }

    /**
     * Any other remaining file should be copied into the "extras" path, e.g. `C:\php\extras\my-php-ext\.`
     *
     * @return non-empty-string
     */
    private function copyExtraFile(TargetPlatform $targetPlatform, DownloadedPackage $downloadedPackage, SplFileInfo $file): string
    {
        $destinationFullFilename = dirname($targetPlatform->phpBinaryPath->phpBinaryPath) . DIRECTORY_SEPARATOR
            . 'extras' . DIRECTORY_SEPARATOR
            . $downloadedPackage->package->extensionName()->name() . DIRECTORY_SEPARATOR
            . substr($file->getPathname(), strlen($downloadedPackage->extractedSourcePath) + 1);

        $destinationPath = dirname($destinationFullFilename);

        if (! file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        if (! copy($file->getPathname(), $destinationFullFilename) || ! file_exists($destinationFullFilename) && ! is_file($destinationFullFilename)) {
            throw new RuntimeException('Failed to copy to ' . $destinationFullFilename);
        }

        return $destinationFullFilename;
    }
}
