<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Package\CompletePackageInterface;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\RepositorySet;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\TargetPhp\ResolveTargetPhpToPlatformRepository;
use Php\Pie\Platform\TargetPlatform;

use function preg_match;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ResolveDependencyWithComposer implements DependencyResolver
{
    public function __construct(
        private readonly RepositorySet $repositorySet,
        private readonly ResolveTargetPhpToPlatformRepository $resolveTargetPhpToPlatformRepository,
    ) {
    }

    public function __invoke(TargetPlatform $targetPlatform, string $packageName, string|null $requestedVersion): Package
    {
        $preferredStability = 'stable';
        $repoSetFlags       = 0;

        /** Stability options from {@see https://getcomposer.org/doc/04-schema.md#minimum-stability} */
        if ($requestedVersion !== null && preg_match('#@(dev|alpha|beta|RC|stable)$#', $requestedVersion, $matches)) {
            $preferredStability = $matches[1];
            $repoSetFlags      |= RepositorySet::ALLOW_UNACCEPTABLE_STABILITIES;
        }

        $package = (new VersionSelector(
            $this->repositorySet,
            ($this->resolveTargetPhpToPlatformRepository)($targetPlatform->phpBinaryPath),
        ))
            ->findBestCandidate($packageName, $requestedVersion, $preferredStability, null, $repoSetFlags);

        if (! $package instanceof CompletePackageInterface) {
            throw UnableToResolveRequirement::fromRequirement($packageName, $requestedVersion);
        }

        if (! ExtensionType::isValid($package->getType())) {
            throw UnableToResolveRequirement::toPhpOrZendExtension($package, $packageName, $requestedVersion);
        }

        return Package::fromComposerCompletePackage($package);
    }
}
