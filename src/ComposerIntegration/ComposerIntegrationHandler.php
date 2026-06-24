<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Composer;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterFactory;
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\Package\CompleteAliasPackage;
use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\DependencyResolver\ResolvedPackageRequest;
use Php\Pie\ExtensionName;
use Php\Pie\Platform;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Emoji;
use Psr\Container\ContainerInterface;

use function array_map;
use function assert;
use function file_exists;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class ComposerIntegrationHandler
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly QuieterConsoleIO $arrayCollectionIo,
        private readonly VendorCleanup $vendorCleanup,
    ) {
    }

    private function addPackageIntoPieJson(
        ResolvedPackageRequest $resolvedPackageRequest,
        Composer $composer,
        TargetPlatform $targetPlatform,
        PieJsonEditor $pieJsonEditor,
    ): void {
        $versionSelector = VersionSelectorFactory::make($composer, $resolvedPackageRequest->requestedPackageAndVersion, $targetPlatform);

        $recommendedRequireVersion = $resolvedPackageRequest->requestedPackageAndVersion->version;

        // If user did not request a specific require version, use Composer to recommend one for the pie.json
        if ($recommendedRequireVersion === null) {
            $recommendedRequireVersion = $versionSelector->findRecommendedRequireVersion($resolvedPackageRequest->piePackage->composerPackage());
        }

        if ($resolvedPackageRequest->piePackage->isBundledPhpExtension()) {
            $stability       = $resolvedPackageRequest->piePackage->composerPackage()->getStability();
            $stabilitySuffix = '';
            if ($stability !== 'stable') {
                $stabilitySuffix = '@' . $stability;
            }

            $recommendedRequireVersion = '*' . $stabilitySuffix;
        }

        // Write the new requirement to pie.json; because we later essentially just do a `composer install` using that file
        $pieJsonEditor->addRequire(
            $resolvedPackageRequest->requestedPackageAndVersion->package,
            $recommendedRequireVersion !== '' ? $recommendedRequireVersion : '*',
        );
    }

    /** @param list<ResolvedPackageRequest> $resolvedRequestedPackages */
    public function runInstall(
        array $resolvedRequestedPackages,
        Composer $composer,
        TargetPlatform $targetPlatform,
        bool $forceInstallPackageVersion,
        bool $runCleanup,
    ): void {
        $pieComposerJson        = Platform::getPieJsonFilename($targetPlatform);
        $pieJsonEditor          = PieJsonEditor::fromTargetPlatform($targetPlatform);
        $originalPieJsonContent = $pieJsonEditor->currentContent();

        array_map(
            fn (ResolvedPackageRequest $resolvedPackageRequest) => $this->addPackageIntoPieJson(
                $resolvedPackageRequest,
                $composer,
                $targetPlatform,
                $pieJsonEditor,
            ),
            $resolvedRequestedPackages,
        );

        // Refresh the Composer instance so it re-reads the updated pie.json
        $composer = PieComposerFactory::recreatePieComposer($this->container, $composer);

        foreach ($composer->getRepositoryManager()->getLocalRepository()->getPackages() as $localRepoPackage) {
            $extName = ExtensionName::determineFromComposerPackage($localRepoPackage);

            if ($localRepoPackage instanceof CompleteAliasPackage) {
                $localRepoPackage = $localRepoPackage->getAliasOf();
            }

            assert($localRepoPackage instanceof CompletePackageInterface);
            $piePackage            = Package::fromComposerCompletePackage($localRepoPackage);
            $installedJsonMetadata = $piePackage->installedJsonMetadata();
            $status                = $piePackage->verifyPackageStatus($targetPlatform);

            $this->arrayCollectionIo->write(sprintf(
                'Install status %s (%s) status=%s',
                $localRepoPackage->getName(),
                $extName->name(),
                $status->description(),
            ), verbosity: IOInterface::VERY_VERBOSE);

            if ($status->isVerified() && ! $forceInstallPackageVersion) {
                $this->arrayCollectionIo->write(sprintf(
                    '%s PIE package %s (%s) is already installed and verified.',
                    Emoji::GREEN_CHECKMARK,
                    $localRepoPackage->getName(),
                    $extName->name(),
                ), verbosity: IOInterface::QUIET);
                continue;
            }

            if (! $installedJsonMetadata->isInstalled() && $installedJsonMetadata->isBuilt()) {
                $this->arrayCollectionIo->write(sprintf(
                    '%s PIE package %s (%s) was previously built but not installed.',
                    Emoji::INFO,
                    $localRepoPackage->getName(),
                    $extName->name(),
                ), verbosity: IOInterface::VERBOSE);
            }

            if (! $installedJsonMetadata->isInstalled() && ! $installedJsonMetadata->isBuilt() && $installedJsonMetadata->isDownloaded()) {
                $this->arrayCollectionIo->write(sprintf(
                    '%s PIE package %s (%s) was previously downloaded but not built.',
                    Emoji::INFO,
                    $localRepoPackage->getName(),
                    $extName->name(),
                ), verbosity: IOInterface::VERBOSE);
            }

            $this->arrayCollectionIo->write(sprintf(
                '%s Package status of %s (%s) is not yet verified, adding to install candidates: %s',
                Emoji::WARNING,
                $localRepoPackage->getName(),
                $extName->name(),
                $status->description(),
            ));
            $composer->getRepositoryManager()->getLocalRepository()->removePackage($localRepoPackage);
        }

        $composerInstaller = PieComposerInstaller::createWithPhpBinary(
            $targetPlatform->phpBinaryPath,
            ResolvedPackageRequest::extensionNames($resolvedRequestedPackages),
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
            $composerInstaller->setUpdateAllowList(ResolvedPackageRequest::requestedPackageNames($resolvedRequestedPackages));
        }

        $resultCode = $composerInstaller->run();

        if ($resultCode !== Installer::ERROR_NONE) {
            // Revert composer.json change
            $pieJsonEditor->revert($originalPieJsonContent);

            throw ComposerRunFailed::fromExitCode($resultCode);
        }

        if (! $runCleanup) {
            return;
        }

        ($this->vendorCleanup)($composer);
    }

    /** @param list<ResolvedPackageRequest> $resolvedPackagesToRemove */
    public function runUninstall(
        array $resolvedPackagesToRemove,
        Composer $composer,
        TargetPlatform $targetPlatform,
    ): void {
        // Write the new requirement to pie.json; because we later essentially just do a `composer install` using that file
        $pieComposerJson        = Platform::getPieJsonFilename($targetPlatform);
        $pieJsonEditor          = PieJsonEditor::fromTargetPlatform($targetPlatform);
        $originalPieJsonContent = $pieJsonEditor->currentContent();

        foreach ($resolvedPackagesToRemove as $resolvedPackageRequest) {
            $pieJsonEditor->removeRequire($resolvedPackageRequest->requestedPackageAndVersion->package);
        }

        // Refresh the Composer instance so it re-reads the updated pie.json
        $composer = PieComposerFactory::recreatePieComposer($this->container, $composer);

        $composerInstaller = PieComposerInstaller::createWithPhpBinary(
            $targetPlatform->phpBinaryPath,
            ResolvedPackageRequest::extensionNames($resolvedPackagesToRemove),
            $this->arrayCollectionIo,
            $composer,
        );
        $composerInstaller
            ->setAllowedTypes(['php-ext', 'php-ext-zend'])
            ->setInstall(true)
            ->setIgnoredTypes([])
            ->setDryRun(false)
            ->setDownloadOnly(false);

        if (file_exists(PieComposerFactory::getLockFile($pieComposerJson))) {
            $composerInstaller->setUpdate(true);
            $composerInstaller->setUpdateAllowList(ResolvedPackageRequest::requestedPackageNames($resolvedPackagesToRemove));
        }

        $resultCode = $composerInstaller->run();

        if ($resultCode !== Installer::ERROR_NONE) {
            // Revert composer.json change
            $pieJsonEditor->revert($originalPieJsonContent);

            throw ComposerRunFailed::fromExitCode($resultCode);
        }
    }
}
