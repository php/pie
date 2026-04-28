<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Composer\Composer;
use Composer\Package\BasePackage;
use Composer\Package\CompletePackageInterface;
use InvalidArgumentException;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;

use function array_filter;
use function array_map;
use function array_values;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class InstalledPiePackages
{
    /**
     * Returns a list of PIE packages according to PIE; this does NOT check if
     * the extension is actually enabled in the target PHP.
     */
    public function allPiePackages(Composer $composer): PiePackageList
    {
        return new PiePackageList(array_values(array_map(
            static function (CompletePackageInterface $package): Package {
                return Package::fromComposerCompletePackage($package);
            },
            array_filter(
                $composer
                    ->getRepositoryManager()
                    ->getLocalRepository()
                    ->getPackages(),
                static function (BasePackage $basePackage): bool {
                    try {
                        ExtensionName::determineFromComposerPackage($basePackage);
                    } catch (InvalidArgumentException) {
                        return false;
                    }

                    return $basePackage instanceof CompletePackageInterface;
                },
            ),
        )));
    }
}
