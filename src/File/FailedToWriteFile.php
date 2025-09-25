<?php

declare(strict_types=1);

namespace Php\Pie\File;

use Php\Pie\Util\CaptureErrors;
use RuntimeException;

use function array_column;
use function implode;
use function sprintf;

/** @import-type CapturedErrorList from CaptureErrors */
class FailedToWriteFile extends RuntimeException
{
    /** @param CapturedErrorList $recorded */
    public static function fromFilePutContentErrors(string $filename, array $recorded): self
    {
        return new self(sprintf(
            "Failed to write file %s.\n\nErrors:\n - %s",
            $filename,
            implode("\n - ", array_column($recorded, 'message')),
        ));
    }

    public static function fromNoPermissions(string $filename): self
    {
        return new self(sprintf(
            'Failed to write file %s as PIE does not have enough permissions',
            $filename,
        ));
    }
}
