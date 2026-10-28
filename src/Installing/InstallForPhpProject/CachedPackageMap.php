<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use DateTimeImmutable;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class CachedPackageMap
{
    public function __construct(
        public readonly ExtensionToPackageSelections $map,
        public readonly string $etag,
        public readonly DateTimeImmutable $expiry,
    ) {
    }
}
