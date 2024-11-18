<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use RuntimeException;

use function sprintf;

class ExtensionBinaryNotFound extends RuntimeException
{
    public static function fromExpectedBinary(string $expectedBinaryName): self
    {
        return new self(sprintf(
            'Build complete, but expected %s does not exist.',
            $expectedBinaryName,
        ));
    }
}
