<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Php\Pie\DependencyResolver\Package;

use function is_string;
use function realpath;
use function str_replace;

use const DIRECTORY_SEPARATOR;

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

    public static function fromPackageAndExtractedPath(Package $package, string $extractedSourcePath): self
    {
        if ($package->buildPath() !== null) {
            $extractedSourcePathWithBuildPath = realpath(
                $extractedSourcePath
                . DIRECTORY_SEPARATOR
                . str_replace('{version}', $package->version(), $package->buildPath()),
            );

            if (is_string($extractedSourcePathWithBuildPath)) {
                $extractedSourcePath = $extractedSourcePathWithBuildPath;
            }
        }

        return new self($package, $extractedSourcePath);
    }
}
