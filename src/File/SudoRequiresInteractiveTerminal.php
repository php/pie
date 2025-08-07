<?php

declare(strict_types=1);

namespace Php\Pie\File;

use RuntimeException;

use function sprintf;

class SudoRequiresInteractiveTerminal extends RuntimeException
{
    public static function fromSudo(string $sudoPath): self
    {
        return new self(sprintf(
            'PIE needed to elevate privileges with %s, but you are running in a non-interactive terminal, so prompting for a password is not possible.',
            $sudoPath,
        ));
    }
}
