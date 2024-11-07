<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Php\Pie\DependencyResolver\Package;

use function is_string;
use function realpath;

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
        private readonly string $extractedSourcePath,
    ) {
    }

    public static function fromPackageAndExtractedPath(Package $package, string $extractedSourcePath): self
    {
        return new self($package, $extractedSourcePath);
    }

    public function getSourcePath(): string
    {
        $path = realpath($this->extractedSourcePath . DIRECTORY_SEPARATOR . $this->package->extensionSource);

        if (! is_string($path)) {
            return $this->extractedSourcePath;
        }

        return $path;
    }
}
