<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\PartialComposer;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use Php\Pie\Building\Build;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionType;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class PiePackageInstaller extends LibraryInstaller
{
    public function __construct(
        IOInterface $io,
        PartialComposer $composer,
        ExtensionType $type,
        Filesystem $filesystem,
        private readonly Build $builder,
        private readonly PieComposerRequest $composerRequest,
    ) {
        parent::__construct($io, $composer, $type->value, $filesystem);
    }

    /** @inheritDoc */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        return parent::install($repo, $package)
            ->then(function () use ($package): void {
                $output = $this->composerRequest->pieOutput;

                if ($this->composerRequest->requestedPackage->package !== $package->getName()) {
                    $output->writeln(sprintf(
                        '<error>Not using PIE to install %s as it was not the expected package %s</error>',
                        $package->getName(),
                        $this->composerRequest->requestedPackage->package,
                    ));

                    return;
                }

                if (! $package instanceof CompletePackage) {
                    $output->writeln(sprintf(
                        '<error>Not using PIE to install %s as it was not a Complete Package</error>',
                        $package->getName(),
                    ));

                    return;
                }

                $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
                    Package::fromComposerCompletePackage($package),
                    $this->getInstallPath($package),
                );

                $output->writeln(sprintf(
                    '<info>Extracted %s source to:</info> %s',
                    $downloadedPackage->package->prettyNameAndVersion(),
                    $downloadedPackage->extractedSourcePath,
                ));

                if ($this->composerRequest->operation->shouldBuild()) {
                    ($this->builder)(
                        $downloadedPackage,
                        $this->composerRequest->targetPlatform,
                        $this->composerRequest->configureOptions,
                        $output,
                    );
                }

                // @todo shouldInstall
            });
    }

    /** @inheritDoc */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        return parent::update($repo, $initial, $target)
            ->then(function (): void {
                // @todo do we need to do things?
                $this->io->write('UPDATE');
            });
    }

    /** @inheritDoc */
    public function cleanup($type, PackageInterface $package, PackageInterface|null $prevPackage = null)
    {
        return parent::cleanup($type, $package, $prevPackage)
            ->then(function (): void {
                // @todo do we need to do things?
                $this->io->write('CLEANUP');
            });
    }
}
