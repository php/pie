<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Composer;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterFactory;
use Composer\Package\CompletePackageInterface;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\ComposerIntegration\VersionSelectorFactory;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Symfony\Component\Console\Output\OutputInterface;

use function in_array;
use function preg_match;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ResolveDependencyWithComposer implements DependencyResolver
{
    public function __construct(
        private readonly OutputInterface $output,
        private readonly QuieterConsoleIO $arrayCollectionIo,
    ) {
    }

    public function __invoke(
        Composer $composer,
        TargetPlatform $targetPlatform,
        RequestedPackageAndVersion $requestedPackageAndVersion,
        bool $forceInstallPackageVersion,
    ): Package {
        $versionSelector = VersionSelectorFactory::make($composer, $requestedPackageAndVersion, $targetPlatform);

        $package = $versionSelector->findBestCandidate(
            $requestedPackageAndVersion->package,
            $requestedPackageAndVersion->version,
            platformRequirementFilter: PlatformRequirementFilterFactory::fromBoolOrList($forceInstallPackageVersion),
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

        if (! $forceInstallPackageVersion) {
            $this->assertBuildProviderProvidersBundledExtensions($targetPlatform, $piePackage, $forceInstallPackageVersion);
            $this->assertCompatibleOsFamily($targetPlatform, $piePackage);
            $this->assertCompatibleThreadSafetyMode($targetPlatform->threadSafety, $piePackage);
        }

        return $piePackage;
    }

    private function assertCompatibleThreadSafetyMode(ThreadSafetyMode $threadSafetyMode, Package $resolvedPackage): void
    {
        if ($threadSafetyMode === ThreadSafetyMode::NonThreadSafe && ! $resolvedPackage->supportNts()) {
            throw IncompatibleThreadSafetyMode::ztsExtensionOnNtsPlatform();
        }

        if ($threadSafetyMode === ThreadSafetyMode::ThreadSafe && ! $resolvedPackage->supportZts()) {
            throw IncompatibleThreadSafetyMode::ntsExtensionOnZtsPlatform();
        }
    }

    private function assertCompatibleOsFamily(TargetPlatform $targetPlatform, Package $resolvedPackage): void
    {
        if ($resolvedPackage->compatibleOsFamilies() !== null && ! in_array($targetPlatform->operatingSystemFamily, $resolvedPackage->compatibleOsFamilies(), true)) {
            throw IncompatibleOperatingSystemFamily::notInCompatibleOperatingSystemFamilies(
                $resolvedPackage->compatibleOsFamilies(),
                $targetPlatform->operatingSystemFamily,
            );
        }

        if ($resolvedPackage->incompatibleOsFamilies() !== null && in_array($targetPlatform->operatingSystemFamily, $resolvedPackage->incompatibleOsFamilies(), true)) {
            throw IncompatibleOperatingSystemFamily::inIncompatibleOperatingSystemFamily(
                $resolvedPackage->incompatibleOsFamilies(),
                $targetPlatform->operatingSystemFamily,
            );
        }
    }

    private function assertBuildProviderProvidersBundledExtensions(TargetPlatform $targetPlatform, Package $piePackage, bool $forceInstallPackageVersion): void
    {
        if (! $piePackage->isBundledPhpExtension()) {
            return;
        }

        $buildProvider           = $targetPlatform->phpBinaryPath->buildProvider();
        $identifiedBuildProvider = false;
        $note                    = '<options=bold,underscore;fg=red>Note:</> ';

        if ($buildProvider === 'https://github.com/docker-library/php') {
            $identifiedBuildProvider = true;
            $this->output->writeln(sprintf(
                '<comment>%sYou should probably use "docker-php-ext-install %s" instead</comment>',
                $note,
                $piePackage->extensionName()->name(),
            ));
        }

        if ($buildProvider === 'Debian') {
            $identifiedBuildProvider = true;
            $this->output->writeln(sprintf(
                '<comment>%sYou should probably use "apt install php%s-%s" or "apt install php-%s" (or similar) instead</comment>',
                $note,
                $targetPlatform->phpBinaryPath->majorMinorVersion(),
                $piePackage->extensionName()->name(),
                $piePackage->extensionName()->name(),
            ));
        }

        if ($buildProvider === 'Remi\'s RPM repository <https://rpms.remirepo.net/> #StandWithUkraine') {
            $identifiedBuildProvider = true;
            $this->output->writeln(sprintf(
                '<comment>%sYou should probably use "dnf install php-%s" instead</comment>',
                $note,
                $piePackage->extensionName()->name(),
            ));
        }

        if ($identifiedBuildProvider && ! $forceInstallPackageVersion) {
            throw BundledPhpExtensionRefusal::forPackage($piePackage);
        }
    }
}
