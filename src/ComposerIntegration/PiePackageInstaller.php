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
        private readonly InstallAndBuildProcess $installAndBuildProcess,
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
                $output = $this->composerRequest->pieOutput;

                if ($this->composerRequest->requestedPackage->package !== $composerPackage->getName()) {
                    $output->writeln(sprintf(
                        '<error>Not using PIE to install %s as it was not the expected package %s</error>',
                        $composerPackage->getName(),
                        $this->composerRequest->requestedPackage->package,
                    ));

                    return null;
                }

                if (! $composerPackage instanceof CompletePackage) {
                    $output->writeln(sprintf(
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
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        return parent::update($repo, $initial, $target)
            ?->then(function () {
                // @todo do we need to do things?
                $this->io->write('UPDATE');

                return null;
            });
    }

    /** @inheritDoc */
    public function cleanup($type, PackageInterface $package, PackageInterface|null $prevPackage = null)
    {
        return parent::cleanup($type, $package, $prevPackage)
            ?->then(function () {
                // @todo do we need to do things?
                $this->io->write('CLEANUP');

                return null;
            });
    }
}
