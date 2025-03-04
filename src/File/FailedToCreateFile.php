<?php

declare(strict_types=1);

namespace Php\Pie\File;

use Php\Pie\Util\CaptureErrors;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function array_column;
use function implode;
use function sprintf;

/** @psalm-import-type CapturedErrorList from CaptureErrors */
class FailedToCreateFile extends RuntimeException
{
    /** @param CapturedErrorList $recorded */
    public static function fromTouchErrors(string $filename, array $recorded): self
    {
        return new self(sprintf(
            "Failed to create file %s.\n\nErrors:\n - %s",
            $filename,
            implode("\n - ", array_column($recorded, 'message')),
        ));
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
