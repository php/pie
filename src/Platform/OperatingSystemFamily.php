<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use function array_map;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
enum OperatingSystemFamily: string
{
    case Windows = 'Windows';
    case Bsd     = 'BSD';
    case Darwin  = 'Darwin';
    case Solaris = 'Solaris';
    case Linux   = 'Linux';
    case Unknown = 'Unknown';

    /** @return array<string> */
    public static function asValuesList(): array
    {
        return array_map(
            static fn (OperatingSystemFamily $osFamily): string => $osFamily->value,
            self::cases(),
        );
    }
}
