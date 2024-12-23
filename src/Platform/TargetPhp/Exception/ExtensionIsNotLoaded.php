<?php

declare(strict_types=1);

namespace Php\Pie\Platform\TargetPhp\Exception;

use Php\Pie\ExtensionName;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use RuntimeException;

use function sprintf;

class ExtensionIsNotLoaded extends RuntimeException
{
    public static function fromExpectedExtension(PhpBinaryPath $php, ExtensionName $extension): self
    {
        return new self(sprintf(
            'Expected extension %s to be loaded in PHP %s, but it was not detected.',
            $extension->name(),
            $php->phpBinaryPath,
        ));
    }
}
