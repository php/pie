<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\BuildTools;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class BuildToolStatus
{
    public function __construct(
        public readonly string $toolNames,
        public readonly bool $found,
        public readonly string|null $packageName,
    ) {
    }
}
