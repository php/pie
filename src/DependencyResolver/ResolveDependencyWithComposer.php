<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Package\CompletePackageInterface;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositorySet;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ResolveDependencyWithComposer implements DependencyResolver
{
    public function __construct(
        private readonly PlatformRepository $platformRepository,
        private readonly RepositorySet $repositorySet,
    ) {
    }

    public function __invoke(string $packageName, string|null $requestedVersion): Package
    {
        $package = (new VersionSelector($this->repositorySet, $this->platformRepository))
            ->findBestCandidate($packageName, $requestedVersion);

        if (! $package instanceof CompletePackageInterface) {
            throw UnableToResolveRequirement::fromRequirement($packageName, $requestedVersion);
        }

        return Package::fromComposerCompletePackage($package);
    }
}
