<?php

declare(strict_types=1);

namespace Php\Pie\Platform\Git;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class Submodule
{
    public function __construct(
        public readonly string $path,
        public readonly string $url,
    ) {
    }
}
