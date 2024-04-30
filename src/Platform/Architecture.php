<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
enum Architecture
{
    case x86;
    case x86_64;
    case arm64;

    /**
     * Architecture naming is inconsistent across various platforms, so try to unify the various labels...
     *
     * @param non-empty-string $architecture
     */
    public static function parseArchitecture(string $architecture): self
    {
        return match ($architecture) {
            'x64', 'x86_64', 'AMD64' => self::x86_64,
            'arm64' => self::arm64,
            default => self::x86,
        };
    }
}
