<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Php\Pie\DependencyResolver\Package;

/** @immutable */
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
