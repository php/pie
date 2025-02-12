<?php

declare(strict_types=1);

namespace Php\Pie\Util;

use RuntimeException;

use function sprintf;

class FileNotFound extends RuntimeException
{
    public static function fromFilename(string $filename): self
    {
        return new self(sprintf(
            'File "%s" does not exist',
            $filename,
        ));
    }
}
