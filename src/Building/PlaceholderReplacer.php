<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use Composer\IO\IOInterface;
use DateTimeInterface;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Safe\Exceptions\FilesystemException;
use SplFileInfo;

use function assert;
use function in_array;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function str_ireplace;

/**
 * This tries to support some of the PECL style replacements, but is less flexible. In PECL, you would define in your
 * package.xml something like;
 *
 *     <tasks:replace from="@package_version@" to="version" type="package-info" />
 *
 * This would perform a replacement on the given file. I [searched through GitHub](https://github.com/search?q=%22tasks%3Areplace%22+language%3AXML&type=code&p=1&l=XML)
 * to find some common replacements to make, and defined them in a mostly hard-coded way, or at least the ones that
 * make sense or low-hanging fruit (all underscores can also use hyphens):
 *
 * - @name@ or @package_name@ - replaces with the extension name (NOT the Composer package name)
 * - @version@ or @package_version@ - replaces with the Composer "pretty" version
 * - @release_date@ - release date according to Composer package, formatted 'Y-m-d\TH:i:sP'
 * - @php_bin@ - path to the PHP binary
 *
 * Some omitted replacements are `@php_dir@` (seems to actually point to PEAR directory, which is redundant anyway),
 * `@bin_dir@` (could get this with PHP_BINDIR constant from the $targetPlatform, but not sure if is worth the time),
 * and a few other variations (@phd_ide_version@ for example, which could be replaced with @package_version@).
 *
 * If this feature needs more flexibility in future, we can look into it, but this implementation seems to be a fairly
 * easy win for some basic replacements anyway...
 */
class PlaceholderReplacer
{
    private const FILE_EXTENSIONS = ['c', 'h'];

    public function replacePlaceholdersWithPlaceholderReplacements(IOInterface $io, TargetPlatform $targetPlatform, DownloadedPackage $downloadedPackage): void
    {
        $replacements = [
            'ext-name' => $downloadedPackage->package->extensionName()->name(),
            'version' => $downloadedPackage->package->composerPackage()->getPrettyVersion(),
            'release-date' => (string) $downloadedPackage->package->composerPackage()->getReleaseDate()?->format(DateTimeInterface::ATOM),
            'php-bin' => $targetPlatform->phpBinaryPath->phpBinaryPath,
        ];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($downloadedPackage->extractedSourcePath)) as $file) {
            assert($file instanceof SplFileInfo);

            if (! $file->isFile() || ! in_array($file->getExtension(), self::FILE_EXTENSIONS)) {
                continue;
            }

            $io->write('Replacing placeholders in: ' . $file->getPathname(), verbosity: IOInterface::VERY_VERBOSE);
            try {
                $this->replaceReplacementsInFile($replacements, $file->getPathname());
            } catch (FilesystemException $e) {
                $io->write('Placeholder replacement failed in : ' . $file->getPathname(), verbosity: IOInterface::VERBOSE);
                $io->write((string) $e, verbosity: IOInterface::VERBOSE);
            }
        }
    }

    /**
     * @param array{ext-name: string, version: string, release-date: string, php-bin: string} $replacements
     *
     * @throws FilesystemException
     */
    private function replaceReplacementsInFile(array $replacements, string $filename): void
    {
        file_put_contents(
            $filename,
            str_ireplace(
                [
                    '@name@',
                    '@package_name@',
                    '@package-name@',
                    '@package_version@',
                    '@package-version@',
                    '@release_date@',
                    '@release-date@',
                    '@php_bin@',
                    '@php-bin@',
                ],
                [
                    $replacements['ext-name'],
                    $replacements['ext-name'],
                    $replacements['ext-name'],
                    $replacements['version'],
                    $replacements['version'],
                    $replacements['release-date'],
                    $replacements['release-date'],
                    $replacements['php-bin'],
                    $replacements['php-bin'],
                ],
                file_get_contents($filename),
            ),
        );
    }
}
