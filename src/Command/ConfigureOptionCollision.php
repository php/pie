<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Php\Pie\DependencyResolver\Package;
use RuntimeException;

use function sprintf;

/** @internal */
class ConfigureOptionCollision extends RuntimeException
{
    public static function forOptionName(string $optionName, Package $firstPackage, Package $secondPackage): self
    {
        return new self(sprintf(
            'Both %s and %s declare a configure option named --%s, so PIE cannot determine which package the option is intended for. Please run `pie install` for each package individually instead.',
            $firstPackage->name(),
            $secondPackage->name(),
            $optionName,
        ));
    }
}
