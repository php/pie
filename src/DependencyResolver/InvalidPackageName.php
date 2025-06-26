<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use RuntimeException;

use function sprintf;

class InvalidPackageName extends RuntimeException
{
    public function __construct(string $message, public readonly RequestedPackageAndVersion $requestedPackageAndVersion)
    {
        parent::__construct($message);
    }

    public static function fromMissingForwardSlash(RequestedPackageAndVersion $requestedPackageAndVersion): self
    {
        return new self(
            sprintf(
                'Requested package name "%s" is invalid; it should contain a forward slash, like "vendor/package".',
                $requestedPackageAndVersion->package,
            ),
            $requestedPackageAndVersion,
        );
    }
}
