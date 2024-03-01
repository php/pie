<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\IO\NullIO;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\RepositorySet;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ResolveDependencyWithComposer implements DependencyResolver
{
    public function __construct(
        private readonly PlatformRepository $platformRepository,
        private readonly RepositorySet $repositorySet,
    ) {
    }

    public static function factory(): self
    {
        $repositorySet = new RepositorySet();
        $repositorySet->addRepository(new CompositeRepository(RepositoryFactory::defaultReposWithDefaultManager(new NullIO())));

        return new self(
            new PlatformRepository(),
            $repositorySet,
        );
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
