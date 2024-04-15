<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Php\Pie\DependencyResolver\Package;

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
        return new self($package, $extractedSourcePath);
    }
}
