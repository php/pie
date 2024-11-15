<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Composer;
use Composer\Package\CompletePackageInterface;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\ComposerIntegration\VersionSelectorFactory;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;

use function preg_match;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ResolveDependencyWithComposer implements DependencyResolver
{
    public function __construct(
        private readonly QuieterConsoleIO $arrayCollectionIo,
    ) {
    }

    public function __invoke(Composer $composer, TargetPlatform $targetPlatform, RequestedPackageAndVersion $requestedPackageAndVersion): Package
    {
        $versionSelector = VersionSelectorFactory::make($composer, $requestedPackageAndVersion, $targetPlatform);

        $package = $versionSelector->findBestCandidate(
            $requestedPackageAndVersion->package,
            $requestedPackageAndVersion->version,
            io: $this->arrayCollectionIo,
        );

        if (! $package instanceof CompletePackageInterface) {
            throw UnableToResolveRequirement::fromRequirement($requestedPackageAndVersion, $this->arrayCollectionIo);
        }

        /**
         * If a specific commit hash is requested, override the references in the package. This is approximately what
         * Composer does anyway:
         *
         * > ArrayLoader::parseLinks is in charge of this, it drops commit refs, for package resolution purposes we
         * > only use dev-main, but we ensure in the PoolBuilder that root references (#...) are set so the dev-main
         * > package has its source and dist refs overridden to be whatever you specify and that applies at install
         * > time then but package metadata is only read from the branch's head
         */
        if ($requestedPackageAndVersion->version !== null && preg_match('/#([a-f0-9]{40})$/', $requestedPackageAndVersion->version, $matches)) {
            $package->setSourceDistReferences($matches[1]);
        }

        if (! ExtensionType::isValid($package->getType())) {
            throw UnableToResolveRequirement::toPhpOrZendExtension($package, $requestedPackageAndVersion);
        }

        $piePackage = Package::fromComposerCompletePackage($package);

        $this->assertCompatibleThreadSafetyMode($targetPlatform->threadSafety, $piePackage);

        return $piePackage;
    }

    private function assertCompatibleThreadSafetyMode(ThreadSafetyMode $threadSafetyMode, Package $resolvedPackage): void
    {
        if ($threadSafetyMode === ThreadSafetyMode::NonThreadSafe && ! $resolvedPackage->supportNts) {
            throw IncompatibleThreadSafetyMode::ztsExtensionOnNtsPlatform();
        }

        if ($threadSafetyMode === ThreadSafetyMode::ThreadSafe && ! $resolvedPackage->supportZts) {
            throw IncompatibleThreadSafetyMode::ntsExtensionOnZtsPlatform();
        }
    }
}
