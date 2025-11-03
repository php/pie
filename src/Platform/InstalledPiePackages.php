<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Composer\Composer;
use Composer\Package\BasePackage;
use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;

use function array_combine;
use function array_filter;
use function array_map;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @phpstan-type ListOfPiePackages = array<non-empty-string, Package>
 */
class InstalledPiePackages
{
    /**
     * Returns a list of PIE packages according to PIE; this does NOT check if
     * the extension is actually enabled in the target PHP.
     *
     * @return ListOfPiePackages
     */
    public function allPiePackages(Composer $composer): array
    {
        $composerInstalledPackages = array_map(
            static function (CompletePackageInterface $package): Package {
                return Package::fromComposerCompletePackage($package);
            },
            array_filter(
                $composer
                    ->getRepositoryManager()
                    ->getLocalRepository()
                    ->getPackages(),
                static function (BasePackage $basePackage): bool {
                    return $basePackage instanceof CompletePackageInterface;
                },
            ),
        );

        return array_combine(
            array_map(
            /** @return non-empty-string */
                static function (Package $package): string {
                    return match ($package->extensionName()->name()) {
                        'core' => 'Core',
                        'spl' => 'SPL',
                        'phar' => 'Phar',
                        'reflection' => 'Reflection',
                        'pdo' => 'PDO',
                        'ffi' => 'FFI',
                        'opcache' => 'Zend OPcache',
                        'simplexml' => 'SimpleXML',
                        default => $package->extensionName()->name(),
                    };
                },
                $composerInstalledPackages,
            ),
            $composerInstalledPackages,
        );
    }
}
