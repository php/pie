<?php

declare(strict_types=1);

namespace Php\Pie\File;

use RuntimeException;
use Throwable;

use function sprintf;

class FailedToWriteFile extends RuntimeException
{
    public static function fromFilePutContentError(string $filename, Throwable $previous): self
    {
        return new self(
            sprintf(
                'Failed to write file %s: %s',
                $filename,
                $previous->getMessage(),
            ),
            previous: $previous,
        );
    }

    public static function fromNoPermissions(string $filename, Throwable|null $previous): self
    {
        return new self(
            sprintf(
                'Failed to write file %s as PIE does not have enough permissions',
                $filename,
            ),
            previous: $previous,
        );
    }
}
