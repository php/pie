<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use function str_contains;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class RequestedPackageAndVersion
{
    /**
     * @param non-empty-string      $package
     * @param non-empty-string|null $version
     *
     * @throws InvalidPackageName
     */
    public function __construct(
        public readonly string $package,
        public readonly string|null $version,
    ) {
        if (! str_contains($this->package, '/')) {
            throw InvalidPackageName::fromMissingForwardSlash($this);
        }
    }
}
