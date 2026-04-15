<?php

declare(strict_types=1);

namespace Php\Pie\File;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Throwable;

use function sprintf;

class FailedToCreateFile extends RuntimeException
{
    public static function fromTouchError(string $filename, Throwable $previous): self
    {
        return new self(
            sprintf(
                'Failed to create file %s: %s',
                $filename,
                $previous->getMessage(),
            ),
            previous: $previous,
        );
    }

    public static function fromNoPermissions(string $filename): self
    {
        return new self(sprintf(
            'Failed to create file %s as PIE does not have enough permissions',
            $filename,
        ));
    }

    public static function fromSudoTouchProcessFailed(string $filename, ProcessFailedException $processFailed): self
    {
        return new self(
            sprintf(
                'Failed to create file %s using sudo touch: %s',
                $filename,
                $processFailed->getMessage(),
            ),
            previous: $processFailed,
        );
    }
}
