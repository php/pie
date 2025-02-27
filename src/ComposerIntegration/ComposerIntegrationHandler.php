<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Composer;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterFactory;
use Composer\Installer;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Platform;
use Php\Pie\Platform\TargetPlatform;
use Psr\Container\ContainerInterface;

use function file_exists;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class ComposerIntegrationHandler
{
    /** @psalm-api */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly QuieterConsoleIO $arrayCollectionIo,
        private readonly VendorCleanup $vendorCleanup,
    ) {
    }

    public function __invoke(
        Package $package,
        Composer $composer,
        TargetPlatform $targetPlatform,
        RequestedPackageAndVersion $requestedPackageAndVersion,
        bool $forceInstallPackageVersion,
    ): void {
        $versionSelector = VersionSelectorFactory::make($composer, $requestedPackageAndVersion, $targetPlatform);

        $recommendedRequireVersion = $requestedPackageAndVersion->version;

        // If user did not request a specific require version, use Composer to recommend one for the pie.json
        if ($recommendedRequireVersion === null) {
            $recommendedRequireVersion = $versionSelector->findRecommendedRequireVersion($package->composerPackage());
        }

        // Write the new requirement to pie.json; because we later essentially just do a `composer install` using that file
        $pieComposerJson        = Platform::getPieJsonFilename($targetPlatform);
        $pieJsonEditor          = PieJsonEditor::fromTargetPlatform($targetPlatform);
        $originalPieJsonContent = $pieJsonEditor->addRequire(
            $requestedPackageAndVersion->package,
            $recommendedRequireVersion !== '' ? $recommendedRequireVersion : '*',
        );

        // Refresh the Composer instance so it re-reads the updated pie.json
        $composer = PieComposerFactory::recreatePieComposer($this->container, $composer);

        // Removing the package from the local repository will trick Composer into "re-installing" it :)
        foreach ($composer->getRepositoryManager()->getLocalRepository()->findPackages($requestedPackageAndVersion->package) as $pkg) {
            $composer->getRepositoryManager()->getLocalRepository()->removePackage($pkg);
        }

        $composerInstaller = PieComposerInstaller::createWithPhpBinary(
            $targetPlatform->phpBinaryPath,
            $package->extensionName(),
            $this->arrayCollectionIo,
            $composer,
        );
        $composerInstaller
            ->setAllowedTypes(['php-ext', 'php-ext-zend'])
            ->setInstall(true)
            ->setIgnoredTypes([])
            ->setDryRun(false)
            ->setPlatformRequirementFilter(PlatformRequirementFilterFactory::fromBoolOrList($forceInstallPackageVersion))
            ->setDownloadOnly(false);

        if (file_exists(PieComposerFactory::getLockFile($pieComposerJson))) {
            $composerInstaller->setUpdate(true);
            $composerInstaller->setUpdateAllowList([$requestedPackageAndVersion->package]);
        }

        $resultCode = $composerInstaller->run();

        if ($resultCode !== Installer::ERROR_NONE) {
            // Revert composer.json change
            $pieJsonEditor->revert($originalPieJsonContent);

            throw ComposerRunFailed::fromExitCode($resultCode);
        }

        ($this->vendorCleanup)($composer);
    }
}
