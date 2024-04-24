<?php

declare(strict_types=1);

namespace Php\Pie\Downloading\Exception;

use Php\Pie\DependencyResolver\Package;
use RuntimeException;

use function sprintf;

class CouldNotFindReleaseAsset extends RuntimeException
{
    public static function forPackage(Package $package, string $expectedAssetName): self
    {
        return new self(sprintf(
            'Could not find release asset for %s named "%s"',
            $package->prettyNameAndVersion(),
            $expectedAssetName,
        ));
    }
}
