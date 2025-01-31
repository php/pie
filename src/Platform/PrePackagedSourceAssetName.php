<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Php\Pie\DependencyResolver\Package;

use function sprintf;
use function strtolower;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class PrePackagedSourceAssetName
{
    /** @psalm-suppress UnusedConstructor */
    private function __construct()
    {
    }

    /** @return non-empty-list<non-empty-string> */
    public static function packageNames(Package $package): array
    {
        return [
            strtolower(sprintf(
                'php_%s-%s-src.tgz',
                $package->extensionName()->name(),
                $package->version(),
            )),
            strtolower(sprintf(
                'php_%s-%s-src.zip',
                $package->extensionName()->name(),
                $package->version(),
            )),
            // @todo remove this:
            strtolower(sprintf(
                '%s-%s.tgz',
                $package->extensionName()->name(),
                $package->version(),
            )),
        ];
    }
}
