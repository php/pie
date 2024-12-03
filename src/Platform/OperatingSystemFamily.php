<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use function array_map;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
enum OperatingSystemFamily: string
{
    case Windows = 'windows';
    case Bsd     = 'bsd';
    case Darwin  = 'darwin';
    case Solaris = 'solaris';
    case Linux   = 'linux';
    case Unknown = 'unknown';

    /** @return non-empty-list<non-empty-string> */
    public static function asValuesList(): array
    {
        return array_map(
            static fn (OperatingSystemFamily $osFamily): string => $osFamily->value,
            self::cases(),
        );
    }
}
