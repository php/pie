<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\File\BinaryFile;
use RuntimeException;
use Throwable;

use function sprintf;

class FailedToRemoveExtension extends RuntimeException
{
    public static function withFilename(BinaryFile $extension, Throwable $previous): self
    {
        return new self(
            sprintf(
                'Failed to remove extension file: %s',
                $extension->filePath,
            ),
            previous: $previous,
        );
    }
}
