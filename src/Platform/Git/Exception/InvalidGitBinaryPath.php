<?php

declare(strict_types=1);

namespace Php\Pie\Platform\Git\Exception;

use RuntimeException;

use function sprintf;

class InvalidGitBinaryPath extends RuntimeException
{
    public static function fromNonExistentGitBinary(string $phpBinaryPath): self
    {
        return new self(sprintf(
            'The git binary at "%s" does not exist',
            $phpBinaryPath,
        ));
    }

    public static function fromNonExecutableGitBinary(string $phpBinaryPath): self
    {
        return new self(sprintf(
            'The git binary at "%s" is not executable',
            $phpBinaryPath,
        ));
    }

    public static function fromInvalidGitBinary(string $phpBinaryPath): self
    {
        return new self(sprintf(
            'The git binary at "%s" does not appear to be a git binary',
            $phpBinaryPath,
        ));
    }
}
