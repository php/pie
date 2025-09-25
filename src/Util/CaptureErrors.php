<?php

declare(strict_types=1);

namespace Php\Pie\Util;

use function restore_error_handler;
use function set_error_handler;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @phpstan-type CapturedErrorList = list<array{level: int, message: string, filename: string, line: int}>
 */
final class CaptureErrors
{
    /**
     * @param callable():T      $code
     * @param CapturedErrorList $captured
     *
     * @return T
     *
     * @template T
     */
    public static function for(callable $code, array &$captured): mixed
    {
        set_error_handler(static function (int $level, string $message, string $filename, int $line) use (&$captured): bool {
            $captured[] = [
                'level' => $level,
                'message' => $message,
                'filename' => $filename,
                'line' => $line,
            ];

            return true;
        });

        $returnValue = $code();

        restore_error_handler();

        return $returnValue;
    }
}
