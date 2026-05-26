<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\Platform\PrePackagedSourceAssetName;
use RuntimeException;
use Safe\Exceptions\FilesystemException;

use function array_map;
use function array_unique;
use function file_exists;
use function is_dir;
use function pathinfo;
use function Safe\realpath;
use function sprintf;
use function str_replace;
use function str_starts_with;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_FILENAME;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class DownloadedPackage
{
    private function __construct(
        public readonly Package $package,
        public readonly string $extractedSourcePath,
    ) {
    }

    private static function unfoldUnarchivedSourcePaths(Package $package, string $extractedSourcePath): string
    {
        // There is already something buildable here, don't need to unfold
        if (
            file_exists($extractedSourcePath . DIRECTORY_SEPARATOR . 'config.m4')
            || file_exists($extractedSourcePath . DIRECTORY_SEPARATOR . 'config.w32')
        ) {
            return $extractedSourcePath;
        }

        $possibleAssetNames = array_unique(array_map(
            static fn (string $assetName): string => pathinfo($assetName, PATHINFO_FILENAME),
            PrePackagedSourceAssetName::packageNames($package),
        ));
        foreach ($possibleAssetNames as $possibleAssetName) {
            if (
                ! file_exists($extractedSourcePath . DIRECTORY_SEPARATOR . $possibleAssetName)
                || ! is_dir($extractedSourcePath . DIRECTORY_SEPARATOR . $possibleAssetName)
            ) {
                continue;
            }

            if (
                file_exists($extractedSourcePath . DIRECTORY_SEPARATOR . $possibleAssetName . DIRECTORY_SEPARATOR . 'config.m4')
                || file_exists($extractedSourcePath . DIRECTORY_SEPARATOR . $possibleAssetName . DIRECTORY_SEPARATOR . 'config.w32')
            ) {
                return $extractedSourcePath . DIRECTORY_SEPARATOR . $possibleAssetName;
            }
        }

        return $extractedSourcePath;
    }

    private static function overrideSourcePathUsingBuildPath(Package $package, string $extractedSourcePath): string
    {
        if ($package->buildPath() === null) {
            return $extractedSourcePath;
        }

        try {
            $candidate = realpath(
                $extractedSourcePath
                . DIRECTORY_SEPARATOR
                . str_replace('{version}', $package->version(), $package->buildPath()),
            );
        } catch (FilesystemException) {
            // Build path does not exist; likely a configuration error
            return $extractedSourcePath;
        }

        $extractedReal = realpath($extractedSourcePath);
        if (! str_starts_with($candidate . DIRECTORY_SEPARATOR, $extractedReal . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException(sprintf(
                'php-ext.build-path %s resolved to %s, which is outside the extract directory %s',
                $package->buildPath(),
                $candidate,
                $extractedReal,
            ));
        }

        return $candidate;
    }

    public static function fromPackageAndExtractedPath(Package $package, string $extractedSourcePath): self
    {
        $sourcePath = self::unfoldUnarchivedSourcePaths($package, $extractedSourcePath);

        if ($package->buildPath() !== null) {
            $sourcePath = self::overrideSourcePathUsingBuildPath($package, $extractedSourcePath);
        }

        return new self($package, $sourcePath);
    }
}
