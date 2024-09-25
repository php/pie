<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use RuntimeException;

class IncompatibleThreadSafetyMode extends RuntimeException
{
    public static function ztsExtensionOnNtsPlatform(): self
    {
        return new self('This extension does not support being installed on a non-Thread Safe PHP installation');
    }

    public static function ntsExtensionOnZtsPlatform(): self
    {
        return new self('This extension does not support being installed on a Thread Safe PHP installation');
    }
}
