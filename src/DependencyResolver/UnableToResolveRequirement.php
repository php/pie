<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use RuntimeException;

use function sprintf;

class UnableToResolveRequirement extends RuntimeException
{
    public static function fromRequirement(string $requiredPackageName, string|null $requiredVersion): self
    {
        return new self(sprintf(
            'Unable to find an installable package %s%s',
            $requiredPackageName,
            $requiredVersion !== null ? sprintf(' for version %s.', $requiredVersion) : '.',
        ));
    }
}
