<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Composer\Package\BasePackage;
use Composer\Package\CompletePackageInterface;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\DependencyResolver\Package;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\NullOutput;

use function array_combine;
use function array_filter;
use function array_map;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @psalm-type ListOfPiePackages = array<non-empty-string, Package>
 */
class InstalledPiePackages
{
    /** @psalm-suppress PossiblyUnusedMethod no direct reference; used in service locator */
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * Returns a list of PIE packages according to PIE; this does NOT check if
     * the extension is actually enabled in the target PHP.
     *
     * @return ListOfPiePackages
     */
    public function allPiePackages(TargetPlatform $targetPlatform): array
    {
        $composerInstalledPackages = array_map(
            static function (CompletePackageInterface $package): Package {
                return Package::fromComposerCompletePackage($package);
            },
            array_filter(
                PieComposerFactory::createPieComposer(
                    $this->container,
                    PieComposerRequest::noOperation(
                        new NullOutput(),
                        $targetPlatform,
                    ),
                )
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
                    return $package->extensionName()->name();
                },
                $composerInstalledPackages,
            ),
            $composerInstalledPackages,
        );
    }
}
