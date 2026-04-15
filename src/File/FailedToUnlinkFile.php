<?php

declare(strict_types=1);

namespace Php\Pie\File;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Throwable;

use function sprintf;

class FailedToUnlinkFile extends RuntimeException
{
    public static function fromUnlinkError(string $filename, Throwable $previous): self
    {
        return new self(
            sprintf(
                'Failed to unlink file %s: %s',
                $filename,
                $previous->getMessage(),
            ),
            previous: $previous,
        );
    }

    public static function fromNoPermissions(string $filename): self
    {
        return new self(sprintf(
            'Failed to unlink file %s as PIE does not have enough permissions',
            $filename,
        ));
    }

    public static function fromSudoRmProcessFailed(string $filename, ProcessFailedException $processFailed): self
    {
        return new self(
            sprintf(
                'Failed to unlink file %s using sudo rm: %s',
                $filename,
                $processFailed->getMessage(),
            ),
            previous: $processFailed,
        );
    }
}
