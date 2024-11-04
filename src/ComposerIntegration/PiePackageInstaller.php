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
use Php\Pie\Installing\Install;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class PiePackageInstaller extends LibraryInstaller
{
    public function __construct(
        IOInterface $io,
        PartialComposer $composer,
        ExtensionType $type,
        Filesystem $filesystem,
        private readonly Build $pieBuild,
        private readonly Install $pieInstall,
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

                $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
                    Package::fromComposerCompletePackage($composerPackage),
                    $this->getInstallPath($composerPackage),
                );

                $output->writeln(sprintf(
                    '<info>Extracted %s source to:</info> %s',
                    $downloadedPackage->package->prettyNameAndVersion(),
                    $downloadedPackage->extractedSourcePath,
                ));

                // @todo QUESTION: why does this not work?
                $composerPackage->setExtra(['test1' => 'test1']);
                $composerPackage->setTransportOptions(['test2' => 'test2']);

                // @todo target platform metadata should go into `pie.lock`

                if ($this->composerRequest->operation->shouldBuild()) {
                    // @todo configureOptions used should go into `pie.lock`
                    // @todo the location + checksum of the built .so should go into `pie.lock`
                    ($this->pieBuild)(
                        $downloadedPackage,
                        $this->composerRequest->targetPlatform,
                        $this->composerRequest->configureOptions,
                        $output,
                    );
                }

                if (! $this->composerRequest->operation->shouldInstall()) {
                    return null;
                }

                // @todo the location of the installed .so should go into `pie.lock`
                ($this->pieInstall)(
                    $downloadedPackage,
                    $this->composerRequest->targetPlatform,
                    $output,
                );

                // @todo should not need this, in theory, need to check
                // try {
                //     $this->installNotification->send($targetPlatform, $downloadedPackage);
                // } catch (FailedToSendInstallNotification $failedToSendInstallNotification) {
                //     if ($output->isVeryVerbose()) {
                //         $output->writeln('Install notification did not send.');
                //         if ($output->isDebug()) {
                //             $output->writeln($failedToSendInstallNotification->__toString());
                //         }
                //     }
                // }

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
