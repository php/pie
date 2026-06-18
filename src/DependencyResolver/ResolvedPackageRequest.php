<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ResolvedPackageRequest
{
    public function __construct(
        public readonly Package $piePackage,
        public readonly RequestedPackageAndVersion $requestedPackageAndVersion,
    ) {
    }
}
