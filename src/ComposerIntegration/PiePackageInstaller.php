<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use Composer\Package\PackageInterface;
use Composer\PartialComposer;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\ExtensionType;

use function array_map;
use function implode;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class PiePackageInstaller extends LibraryInstaller
{
    public function __construct(
        IOInterface $io,
        PartialComposer $composer,
        ExtensionType $type,
        Filesystem $filesystem,
        private readonly InstallAndBuildProcess $installAndBuildProcess,
        private readonly UninstallProcess $uninstallProcess,
        private readonly PieComposerRequest $composerRequest,
    ) {
        parent::__construct($io, $composer, $type->value, $filesystem);
    }

    /** @inheritDoc */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $composerPackage = $package;

        return parent::install($repo, $composerPackage)
            ?->then(function () use ($composerPackage) {
                $io = $this->composerRequest->pieOutput;

                if (! $this->composerRequest->isFor($composerPackage->getName())) {
                    $io->write(
                        sprintf(
                            '<comment>Skipping %s install request from Composer as it was not the expected PIE package(s) %s</comment>',
                            $composerPackage->getName(),
                            implode(', ', array_map(static fn (RequestedPackageAndVersion $req) => $req->package, $this->composerRequest->requestedPackages)),
                        ),
                        verbosity: IOInterface::VERY_VERBOSE,
                    );

                    return null;
                }

                if (! $composerPackage instanceof CompletePackageInterface) {
                    $io->writeError(sprintf(
                        '<error>Not using PIE to install %s as it was not a Complete Package</error>',
                        $composerPackage->getName(),
                    ));

                    return null;
                }

                ($this->installAndBuildProcess)(
                    $this->composer,
                    $this->composerRequest,
                    $composerPackage,
                    $this->getInstallPath($composerPackage),
                );

                return null;
            });
    }

    /** @inheritDoc */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $composerPackage = $package;

        return parent::uninstall($repo, $composerPackage)
            ?->then(function () use ($composerPackage) {
                $io = $this->composerRequest->pieOutput;

                if (! $this->composerRequest->isFor($composerPackage->getName())) {
                    $io->write(
                        sprintf(
                            '<comment>Skipping %s uninstall request from Composer as it was not the expected PIE package(s) %s</comment>',
                            $composerPackage->getName(),
                            implode(', ', array_map(static fn (RequestedPackageAndVersion $req) => $req->package, $this->composerRequest->requestedPackages)),
                        ),
                        verbosity: IOInterface::VERY_VERBOSE,
                    );

                    return null;
                }

                if (! $composerPackage instanceof CompletePackageInterface) {
                    $io->writeError(sprintf(
                        '<error>Not using PIE to install %s as it was not a Complete Package</error>',
                        $composerPackage->getName(),
                    ));

                    return null;
                }

                ($this->uninstallProcess)(
                    $this->composerRequest,
                    $composerPackage,
                );

                return null;
            });
    }
}
