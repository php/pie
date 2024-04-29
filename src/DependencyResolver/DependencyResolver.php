<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Php\Pie\Platform\TargetPhp\PhpBinaryPath;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
interface DependencyResolver
{
    /** @throws UnableToResolveRequirement */
    public function __invoke(PhpBinaryPath $phpBinaryPath, string $packageName, string|null $requestedVersion): Package;
}
