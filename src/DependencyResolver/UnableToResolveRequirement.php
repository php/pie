<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Package\PackageInterface;
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

    public static function toPhpOrZendExtension(PackageInterface $locatedComposerPackage, string $requiredPackageName, string|null $requiredVersion): self
    {
        return new self(sprintf(
            'Package %s was not of type php-ext or php-ext-zend (requested %s%s).',
            $locatedComposerPackage->getName(),
            $requiredPackageName,
            $requiredVersion !== null ? sprintf(' for version %s', $requiredVersion) : '',
        ));
    }
}
