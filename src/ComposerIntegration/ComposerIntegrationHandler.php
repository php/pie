<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Composer;
use Composer\Installer;
use Composer\Json\JsonManipulator;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Platform;
use Php\Pie\Platform\TargetPlatform;
use Psr\Container\ContainerInterface;

use function file_get_contents;
use function file_put_contents;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class ComposerIntegrationHandler
{
    /** @psalm-api */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ArrayCollectionIO $arrayCollectionIo,
    ) {
    }

    public function __invoke(Package $package, Composer $composer, TargetPlatform $targetPlatform, RequestedPackageAndVersion $requestedPackageAndVersion): void
    {
        $versionSelector = VersionSelectorFactory::make($composer, $requestedPackageAndVersion, $targetPlatform);

        $recommendedRequireVersion = $requestedPackageAndVersion->version;

        // @todo check this is reasonable?
        if ($recommendedRequireVersion === null) {
            $recommendedRequireVersion = $versionSelector->findRecommendedRequireVersion($package->composerPackage);
        }

        // Write the new requirement to pie.json; because we later essentially just do a `composer install` using that file
        $pieComposerJson = Platform::getPieJsonFilename();
        $manipulator     = new JsonManipulator(file_get_contents($pieComposerJson));
        $manipulator->addLink('require', $requestedPackageAndVersion->package, $recommendedRequireVersion, true);
        file_put_contents($pieComposerJson, $manipulator->getContents());

        // Refresh the Composer instance so it re-reads the updated pie.json
        $composer = PieComposerFactory::recreatePieComposer($this->container, $composer);

        // Removing the package from the local repository will trick Composer into "re-installing" it :)
        foreach ($composer->getRepositoryManager()->getLocalRepository()->findPackages($requestedPackageAndVersion->package) as $pkg) {
            $composer->getRepositoryManager()->getLocalRepository()->removePackage($pkg);
        }

        // @todo check if you have another ext in pie.json already, it doesn't get changed/installed/etc.
        $composerInstaller = Installer::create($this->arrayCollectionIo, $composer);
        $composerInstaller
            ->setAllowedTypes(['php-ext', 'php-ext-zend'])
            ->setUpdate(true)
            ->setInstall(true)
            ->setIgnoredTypes([])
            ->setDryRun(false)
            ->setDownloadOnly(false);
        $resultCode = $composerInstaller->run();

        if ($resultCode !== Installer::ERROR_NONE) {
            throw ComposerRunFailed::fromExitCode($resultCode);
        }
    }
}
