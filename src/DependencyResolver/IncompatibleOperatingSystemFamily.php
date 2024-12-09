<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Php\Pie\Platform\OperatingSystemFamily;
use RuntimeException;

use function array_map;
use function implode;
use function sprintf;

class IncompatibleOperatingSystemFamily extends RuntimeException
{
    /** @param list<OperatingSystemFamily> $required */
    public static function notInCompatibleOperatingSystemFamilies(array $required, OperatingSystemFamily $current): self
    {
        return new self(sprintf(
            'This extension does not support the "%s" operating system family. It is compatible with the following families: "%s".',
            $current->value,
            implode('", "', array_map(static fn (OperatingSystemFamily $osFamily): string => $osFamily->value, $required)),
        ));
    }

    /** @param list<OperatingSystemFamily> $incompatibleOsFamilies */
    public static function inIncompatibleOperatingSystemFamily(array $incompatibleOsFamilies, OperatingSystemFamily $current): self
    {
        return new self(sprintf(
            'This extension does not support the "%s" operating system family. It is incompatible with the following families: "%s".',
            $current->value,
            implode('", "', array_map(static fn (OperatingSystemFamily $osFamily): string => $osFamily->value, $incompatibleOsFamilies)),
        ));
    }
}
