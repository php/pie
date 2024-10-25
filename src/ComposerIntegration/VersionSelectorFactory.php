<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Composer;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\CompositeRepository;
use Composer\Repository\RepositorySet;
use Php\Pie\DependencyResolver\DetermineMinimumStability;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Platform\TargetPlatform;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class VersionSelectorFactory
{
    /** @psalm-suppress UnusedConstructor */
    private function __construct()
    {
    }

    private static function factoryRepositorySet(Composer $composer, string|null $requestedVersion): RepositorySet
    {
        $repositorySet = new RepositorySet(DetermineMinimumStability::fromRequestedVersion($requestedVersion));
        $repositorySet->addRepository(new CompositeRepository($composer->getRepositoryManager()->getRepositories()));

        return $repositorySet;
    }

    public static function make(
        Composer $composer,
        RequestedPackageAndVersion $requestedPackageAndVersion,
        TargetPlatform $targetPlatform,
    ): VersionSelector {
        return new VersionSelector(
            self::factoryRepositorySet($composer, $requestedPackageAndVersion->version),
            new PhpBinaryPathBasedPlatformRepository($targetPlatform->phpBinaryPath),
        );
    }
}
