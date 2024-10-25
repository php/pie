<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Package\PackageInterface;
use Php\Pie\ComposerIntegration\ArrayCollectionIO;
use RuntimeException;

use function array_map;
use function count;
use function implode;
use function sprintf;
use function strip_tags;

class UnableToResolveRequirement extends RuntimeException
{
    public static function fromRequirement(
        RequestedPackageAndVersion $requestedPackageAndVersion,
        ArrayCollectionIO $io,
    ): self {
        $errors = $io->errors;

        return new self(sprintf(
            'Unable to find an installable package %s%s%s',
            $requestedPackageAndVersion->package,
            $requestedPackageAndVersion->version !== null ? sprintf(' for version %s.', $requestedPackageAndVersion->version) : '.',
            count($errors) ? "\n\n" . implode("\n\n", array_map(static fn ($e) => strip_tags($e), $errors)) : '',
        ));
    }

    public static function toPhpOrZendExtension(PackageInterface $locatedComposerPackage, RequestedPackageAndVersion $requestedPackageAndVersion): self
    {
        return new self(sprintf(
            'Package %s was not of type php-ext or php-ext-zend (requested %s%s).',
            $locatedComposerPackage->getName(),
            $requestedPackageAndVersion->package,
            $requestedPackageAndVersion->version !== null ? sprintf(' for version %s', $requestedPackageAndVersion->version) : '',
        ));
    }
}
