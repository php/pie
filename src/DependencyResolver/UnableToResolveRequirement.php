<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Package\PackageInterface;
use RuntimeException;

use function array_map;
use function count;
use function implode;
use function sprintf;
use function strip_tags;

class UnableToResolveRequirement extends RuntimeException
{
    public static function fromRequirement(
        string $requiredPackageName,
        string|null $requiredVersion,
        ArrayCollectionIO $io,
    ): self {
        $errors = $io->errors;

        return new self(sprintf(
            'Unable to find an installable package %s%s%s',
            $requiredPackageName,
            $requiredVersion !== null ? sprintf(' for version %s.', $requiredVersion) : '.',
            count($errors) ? "\n\n" . implode("\n\n", array_map(static fn ($e) => strip_tags($e), $errors)) : '',
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
