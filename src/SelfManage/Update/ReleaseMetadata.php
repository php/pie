<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Update;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ReleaseMetadata
{
    /**
     * @param non-empty-string $tag
     * @param non-empty-string $downloadUrl
     */
    public function __construct(
        public readonly string $tag,
        public readonly string $downloadUrl,
    ) {
    }
}
