<?php

declare(strict_types=1);

namespace Php\Pie\File;

use RuntimeException;

class SudoNotFoundOnSystem extends RuntimeException
{
    public static function new(): self
    {
        return new self('sudo command was not found on this system');
    }
}
