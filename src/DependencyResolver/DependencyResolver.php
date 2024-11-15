<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Composer;
use Php\Pie\Platform\TargetPlatform;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface DependencyResolver
{
    /** @throws UnableToResolveRequirement */
    public function __invoke(Composer $composer, TargetPlatform $targetPlatform, RequestedPackageAndVersion $requestedPackageAndVersion): Package;
}
