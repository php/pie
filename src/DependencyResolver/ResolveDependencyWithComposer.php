<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Package\CompletePackageInterface;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\RepositorySet;
use Php\Pie\TargetPhp\PhpBinaryPath;
use Php\Pie\TargetPhp\ResolveTargetPhpToPlatformRepository;

use function in_array;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ResolveDependencyWithComposer implements DependencyResolver
{
    public function __construct(
        private readonly RepositorySet $repositorySet,
        private readonly ResolveTargetPhpToPlatformRepository $resolveTargetPhpToPlatformRepository,
    ) {
    }

    public function __invoke(PhpBinaryPath $phpBinaryPath, string $packageName, string|null $requestedVersion): Package
    {
        $package = (new VersionSelector(
            $this->repositorySet,
            ($this->resolveTargetPhpToPlatformRepository)($phpBinaryPath),
        ))
            ->findBestCandidate($packageName, $requestedVersion, 'alpha', null, RepositorySet::ALLOW_UNACCEPTABLE_STABILITIES);

        if (! $package instanceof CompletePackageInterface) {
            throw UnableToResolveRequirement::fromRequirement($packageName, $requestedVersion);
        }

        $type = $package->getType();
        if (! in_array($type, [Package::TYPE_PHP_MODULE, Package::TYPE_ZEND_EXTENSION])) {
            throw UnableToResolveRequirement::toPhpOrZendExtension($package, $packageName, $requestedVersion);
        }

        return Package::fromComposerCompletePackage($package);
    }
}
