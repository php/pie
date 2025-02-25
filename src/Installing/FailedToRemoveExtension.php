<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\BinaryFile;
use RuntimeException;

use function sprintf;

class FailedToRemoveExtension extends RuntimeException
{
    public static function withFilename(BinaryFile $extension): self
    {
        return new self(sprintf(
            'Failed to remove extension file: %s',
            $extension->filePath,
        ));
    }
}
