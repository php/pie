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
            ?->then($this->onlyForRequestedPiePackage(
                $composerPackage,
                'install',
                function (CompletePackageInterface $composerPackage): void {
                    ($this->installAndBuildProcess)(
                        $this->composer,
                        $this->composerRequest,
                        $composerPackage,
                        $this->getInstallPath($composerPackage),
                    );
                },
            ));
    }

    /** @inheritDoc */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        $composerPackage = $target;

        return parent::update($repo, $initial, $target)
            ?->then($this->onlyForRequestedPiePackage(
                $composerPackage,
                'update',
                function (CompletePackageInterface $composerPackage): void {
                    ($this->installAndBuildProcess)(
                        $this->composer,
                        $this->composerRequest,
                        $composerPackage,
                        $this->getInstallPath($composerPackage),
                    );
                },
            ));
    }

    /** @inheritDoc */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $composerPackage = $package;

        return parent::uninstall($repo, $composerPackage)
            ?->then($this->onlyForRequestedPiePackage(
                $composerPackage,
                'uninstall',
                function (CompletePackageInterface $composerPackage): void {
                    ($this->uninstallProcess)(
                        $this->composerRequest,
                        $composerPackage,
                    );
                },
            ));
    }

    /**
     * Wraps the given callback so it only runs when Composer's operation is for a PIE package we actually
     * requested, and that package has full metadata available.
     *
     * @return callable(): null
     */
    private function onlyForRequestedPiePackage(PackageInterface $composerPackage, string $verb, callable $onVerified): callable
    {
        return function () use ($composerPackage, $verb, $onVerified) {
            $io = $this->composerRequest->pieOutput;

            if (! $this->composerRequest->isFor($composerPackage->getName())) {
                $io->write(
                    sprintf(
                        '<comment>Skipping %s %s request from Composer as it was not the expected PIE package(s) %s</comment>',
                        $composerPackage->getName(),
                        $verb,
                        implode(', ', array_map(static fn (RequestedPackageAndVersion $req) => $req->package, $this->composerRequest->requestedPackages)),
                    ),
                    verbosity: IOInterface::VERY_VERBOSE,
                );

                return null;
            }

            if (! $composerPackage instanceof CompletePackageInterface) {
                $io->writeError(sprintf(
                    '<error>Not using PIE to %s %s as it was not a Complete Package</error>',
                    $verb,
                    $composerPackage->getName(),
                ));

                return null;
            }

            $onVerified($composerPackage);

            return null;
        };
    }
}
