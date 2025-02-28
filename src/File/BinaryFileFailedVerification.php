<?php

declare(strict_types=1);

namespace Php\Pie\File;

use RuntimeException;

use function sprintf;
use function substr;

class BinaryFileFailedVerification extends RuntimeException
{
    public static function fromFilenameMismatch(BinaryFile $expected, BinaryFile $actual): self
    {
        return new self(sprintf(
            'Expected file "%s" but actual file was "%s"',
            $expected->filePath,
            $actual->filePath,
        ));
    }

    public static function fromChecksumMismatch(BinaryFile $expected, BinaryFile $actual): self
    {
        return new self(sprintf(
            'File "%s" failed checksum verification. Expected %s..., was %s...',
            $expected->filePath,
            substr($expected->checksum, 0, 8),
            substr($actual->checksum, 0, 8),
        ));
    }
}
