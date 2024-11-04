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
use Php\Pie\ComposerIntegration\PieInstalledJsonMetadataKeys as MetadataKey;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\Install;
use Webmozart\Assert\Assert;

use function array_merge;
use function file_get_contents;
use function hash;
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

                $this->addPieMetadata(
                    $composerPackage,
                    MetadataKey::TargetPlatformPhpPath,
                    $this->composerRequest->targetPlatform->phpBinaryPath->phpBinaryPath,
                );
                $this->addPieMetadata(
                    $composerPackage,
                    MetadataKey::TargetPlatformPhpConfigPath,
                    $this->composerRequest->targetPlatform->phpBinaryPath->phpConfigPath(),
                );
                $this->addPieMetadata(
                    $composerPackage,
                    MetadataKey::TargetPlatformPhpVersion,
                    $this->composerRequest->targetPlatform->phpBinaryPath->version(),
                );
                $this->addPieMetadata(
                    $composerPackage,
                    MetadataKey::TargetPlatformPhpThreadSafety,
                    $this->composerRequest->targetPlatform->threadSafety->name,
                );
                $this->addPieMetadata(
                    $composerPackage,
                    MetadataKey::TargetPlatformPhpWindowsCompiler,
                    $this->composerRequest->targetPlatform->windowsCompiler?->name,
                );
                $this->addPieMetadata(
                    $composerPackage,
                    MetadataKey::TargetPlatformArchitecture,
                    $this->composerRequest->targetPlatform->architecture->name,
                );

                if ($this->composerRequest->operation->shouldBuild()) {
                    $this->addPieMetadata(
                        $composerPackage,
                        MetadataKey::ConfigureOptions,
                        implode(' ', $this->composerRequest->configureOptions),
                    );

                    $builtBinaryFile = ($this->pieBuild)(
                        $downloadedPackage,
                        $this->composerRequest->targetPlatform,
                        $this->composerRequest->configureOptions,
                        $output,
                    );

                    $this->addPieMetadata(
                        $composerPackage,
                        MetadataKey::BuiltBinary,
                        $builtBinaryFile,
                    );

                    $this->addPieMetadata(
                        $composerPackage,
                        MetadataKey::BinaryChecksum,
                        hash('sha256', file_get_contents($builtBinaryFile)),
                    );
                }

                if (! $this->composerRequest->operation->shouldInstall()) {
                    return null;
                }

                $this->addPieMetadata(
                    $composerPackage,
                    MetadataKey::InstalledBinary,
                    ($this->pieInstall)(
                        $downloadedPackage,
                        $this->composerRequest->targetPlatform,
                        $output,
                    ),
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

    private function addPieMetadata(
        CompletePackage $composerPackage,
        MetadataKey $key,
        string|null $value,
    ): void {
        $localRepositoryPackage = $this
            ->composer
            ->getRepositoryManager()
            ->getLocalRepository()
            ->findPackages($composerPackage->getName())[0];
        Assert::isInstanceOf($localRepositoryPackage, CompletePackage::class);

        $localRepositoryPackage->setExtra(array_merge($localRepositoryPackage->getExtra(), [$key->value => $value]));
    }
}
