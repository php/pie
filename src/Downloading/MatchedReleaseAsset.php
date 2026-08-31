<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class MatchedReleaseAsset
{
    /**
     * @param non-empty-string $url
     * @param non-empty-string $filename
     */
    public function __construct(
        public readonly string $url,
        public readonly string $filename,
    ) {
    }
}
