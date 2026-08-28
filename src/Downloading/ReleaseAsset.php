<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ReleaseAsset
{
    /**
     * @param non-empty-string      $url
     * @param non-empty-string      $name
     * @param list<non-empty-string> $headers
     */
    public function __construct(
        public readonly string $url,
        public readonly string $name,
        public readonly array $headers = [],
    ) {
    }
}
