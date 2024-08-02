<?php

declare(strict_types=1);

namespace Php\Pie\Platform\TargetPhp\Exception;

use RuntimeException;

use function sprintf;

class InvalidPhpBinaryPath extends RuntimeException
{
    public static function fromNonExistentPhpBinary(string $phpBinaryPath): self
    {
        return new self(sprintf(
            'The php binary at "%s" does not exist',
            $phpBinaryPath,
        ));
    }

    public static function fromNonExecutablePhpBinary(string $phpBinaryPath): self
    {
        return new self(sprintf(
            'The php binary at "%s" is not executable',
            $phpBinaryPath,
        ));
    }

    public static function fromInvalidPhpBinary(string $phpBinaryPath): self
    {
        return new self(sprintf(
            'The php binary at "%s" does not appear to be a PHP binary',
            $phpBinaryPath,
        ));
    }
}
