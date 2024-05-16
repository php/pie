<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
enum ThreadSafetyMode
{
    case ThreadSafe;
    case NonThreadSafe;

    public function asShort(): string
    {
        return match ($this) {
            self::ThreadSafe => 'ts',
            self::NonThreadSafe => 'nts',
        };
    }
}
