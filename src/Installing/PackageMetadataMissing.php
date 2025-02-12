<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\DependencyResolver\Package;
use RuntimeException;

use function array_diff;
use function array_keys;
use function count;
use function implode;
use function sprintf;

class PackageMetadataMissing extends RuntimeException
{
    /**
     * @param array<string, mixed> $actualMetadata
     * @param list<string>         $wantedKeys
     */
    public static function duringUninstall(Package $package, array $actualMetadata, array $wantedKeys): self
    {
        $missingKeys = array_diff($wantedKeys, array_keys($actualMetadata));

        return new self(sprintf(
            'PIE metadata was missing for package %s. Missing metadata key%s: %s',
            $package->name(),
            count($missingKeys) === 1 ? '' : 's',
            implode(', ', $missingKeys),
        ));
    }
}
