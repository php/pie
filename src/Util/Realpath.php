<?php

declare(strict_types=1);

namespace Php\Pie\Util;

use function Safe\realpath;

final class Realpath
{
    public static function compare(string $path1, string $path2): bool
    {
        return realpath($path1) === realpath($path2);
    }
}
