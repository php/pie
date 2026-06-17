<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Update;

use function str_contains;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class IsBrewInstallation
{
    public function __invoke(string $resolvedPath, string $originalPath): bool
    {
        return str_contains($resolvedPath, '/usr/local/Cellar')
            || str_contains($resolvedPath, '/opt/homebrew/Cellar')
            || str_contains($originalPath, '/usr/local/Cellar')
            || str_contains($originalPath, '/opt/homebrew/Cellar');
    }
}
