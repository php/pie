<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionType;
use Php\Pie\File\FailedToWriteFile;
use Php\Pie\File\SudoFilePut;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_map;
use function array_merge;
use function array_walk;
use function file_exists;
use function file_get_contents;
use function in_array;
use function preg_replace;
use function scandir;
use function sprintf;

use const DIRECTORY_SEPARATOR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class RemoveIniEntryWithFileGetContents implements RemoveIniEntry
{
    /** @return list<string> Returns a list of INI files that were updated to remove the extension */
    public function __invoke(Package $package, TargetPlatform $targetPlatform, OutputInterface $output): array
    {
        $allIniFiles = [];

        $mainIni = $targetPlatform->phpBinaryPath->loadedIniConfigurationFile();
        if ($mainIni !== null) {
            $allIniFiles[] = $mainIni;
        }

        $additionalIniDirectory = $targetPlatform->phpBinaryPath->additionalIniDirectory();
        if ($additionalIniDirectory !== null) {
            $allIniFiles = array_merge(
                array_map(
                    static function (string $path) use ($additionalIniDirectory): string {
                        return $additionalIniDirectory . DIRECTORY_SEPARATOR . $path;
                    },
                    array_filter(
                        scandir($additionalIniDirectory),
                        static function (string $path) use ($additionalIniDirectory): bool {
                            if (in_array($path, ['.', '..'])) {
                                return false;
                            }

                            return file_exists($additionalIniDirectory . DIRECTORY_SEPARATOR . $path);
                        },
                    ),
                ),
                $allIniFiles,
            );
        }

        $regex = sprintf(
            '/^(%s\s*=\s*%s)/m',
            $package->extensionType() === ExtensionType::PhpModule ? 'extension' : 'zend_extension',
            $package->extensionName()->name(),
        );

        $updatedIniFiles = [];
        array_walk(
            $allIniFiles,
            static function (string $iniFile) use (&$updatedIniFiles, $regex, $package, $output): void {
                $currentContent = file_get_contents($iniFile);

                if ($currentContent === false || $currentContent === '') {
                    return;
                }

                $replacedContent = preg_replace(
                    $regex,
                    '; $1 ; removed by PIE',
                    $currentContent,
                );

                if ($replacedContent === null || $replacedContent === $currentContent) {
                    return;
                }

                try {
                    SudoFilePut::contents($iniFile, $replacedContent);
                } catch (FailedToWriteFile) {
                    $output->writeln(sprintf(
                        '<error>Failed to remove extension "%s" from INI file "%s"</error>',
                        $package->extensionName()->name(),
                        $iniFile,
                    ));

                    return;
                }

                $updatedIniFiles[] = $iniFile;
            },
        );

        return $updatedIniFiles;
    }
}
