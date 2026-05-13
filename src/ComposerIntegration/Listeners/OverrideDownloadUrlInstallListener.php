<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration\Listeners;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use Composer\Util\HttpDownloader;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadUrlMethod;
use Php\Pie\Downloading\PackageReleaseAssets;
use Psr\Container\ContainerInterface;
use Throwable;

use function array_walk;
use function pathinfo;

use const PATHINFO_EXTENSION;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class OverrideDownloadUrlInstallListener
{
    public function __construct(
        private readonly Composer $composer,
        private readonly IOInterface $io,
        private readonly ContainerInterface $container,
        private readonly PieComposerRequest $composerRequest,
    ) {
    }

    public static function selfRegister(
        Composer $composer,
        IOInterface $io,
        ContainerInterface $container,
        PieComposerRequest $composerRequest,
    ): void {
        $composer
            ->getEventDispatcher()
            ->addListener(
                InstallerEvents::PRE_OPERATIONS_EXEC,
                new self($composer, $io, $container, $composerRequest),
            );
    }

    public function __invoke(InstallerEvent $installerEvent): void
    {
        $operations = $installerEvent->getTransaction()?->getOperations() ?? [];

        array_walk(
            $operations,
            function (OperationInterface $operation): void {
                if (! $operation instanceof InstallOperation) {
                    return;
                }

                $composerPackage = $operation->getPackage();
                if (! $composerPackage instanceof CompletePackageInterface) {
                    return;
                }

                // Install requests for other packages than the one we want should be ignored
            //                if ($this->composerRequest->requestedPackage->package !== $composerPackage->getName()) {
            //                    return;
            //                }

                $piePackage         = Package::fromComposerCompletePackage($composerPackage);
                $targetPlatform     = $this->composerRequest->targetPlatform;
                $downloadUrlMethods = DownloadUrlMethod::possibleDownloadUrlMethodsForPackage($piePackage, $targetPlatform);

                $selectedDownloadUrlMethod = null;
                $downloadMethodFailures    = [];

                foreach ($downloadUrlMethods as $downloadUrlMethod) {
                    $this->io->write('Trying to download using: ' . $downloadUrlMethod->value, verbosity: IOInterface::VERY_VERBOSE);

                    if ($downloadUrlMethod === DownloadUrlMethod::PrePackagedBinary && $this->composerRequest->configureOptions !== []) {
                        $configureOptionsConflictMessage = 'Cannot use pre-packaged-binary download method, as configure options were passed.';

                        $downloadMethodFailures[$downloadUrlMethod->value] = $configureOptionsConflictMessage;
                        $this->io->write($configureOptionsConflictMessage, verbosity: IOInterface::VERBOSE);

                        continue;
                    }

                    // Exit early if we should just use Composer's normal download
                    if ($downloadUrlMethod === DownloadUrlMethod::ComposerDefaultDownload) {
                        $selectedDownloadUrlMethod = $downloadUrlMethod;
                        break;
                    }

                    try {
                        $possibleAssetNames = $downloadUrlMethod->possibleAssetNames($piePackage, $targetPlatform);
                    } catch (Throwable $t) {
                        $downloadMethodFailures[$downloadUrlMethod->value] = $t->getMessage();
                        $this->io->write('Failed fetching asset names [' . $downloadUrlMethod->value . ']: ' . $t->getMessage(), verbosity: IOInterface::VERBOSE);
                        continue;
                    }

                    if ($possibleAssetNames === null) {
                        $downloadMethodFailures[$downloadUrlMethod->value] = 'No asset names';
                        $this->io->write('Failed fetching asset names [' . $downloadUrlMethod->value . ']: No asset names', verbosity: IOInterface::VERBOSE);
                        continue;
                    }

                    // @todo https://github.com/php/pie/issues/138 will need to depend on the repo type (GH/GL/BB/etc.)
                    $packageReleaseAssets = $this->container->get(PackageReleaseAssets::class);

                    try {
                        $url = $packageReleaseAssets->findMatchingReleaseAssetUrl(
                            $targetPlatform,
                            $piePackage,
                            new HttpDownloader($this->io, $this->composer->getConfig()),
                            $downloadUrlMethod,
                            $possibleAssetNames,
                        );
                    } catch (Throwable $t) {
                        $downloadMethodFailures[$downloadUrlMethod->value] = $t->getMessage();
                        $this->io->write('Failed locating asset [' . $downloadUrlMethod->value . ']: ' . $t->getMessage(), verbosity: IOInterface::VERBOSE);
                        continue;
                    }

                    $this->composerRequest->pieOutput->write('Found prebuilt archive: ' . $url);
                    $composerPackage->setDistUrl($url);

                    // Composer's dist-sha was computed against the original
                    // Packagist URL; once we swap to a release-asset URL the
                    // FileDownloader has nothing to validate the new bytes
                    // against. Surface that so the caller knows HTTPS-to-origin
                    // is the only integrity guarantee left.
                    $this->composerRequest->pieOutput->write(
                        '<warning>Note: dist-sha integrity check is not available for prebuilt-binary URLs; HTTPS to the release-asset origin is the only integrity guarantee.</warning>',
                    );

                    if (pathinfo($url, PATHINFO_EXTENSION) === 'tgz') {
                        $composerPackage->setDistType('tar');
                    }

                    $selectedDownloadUrlMethod = $downloadUrlMethod;
                    break;
                }

                if ($selectedDownloadUrlMethod === null) {
                    throw CouldNotDetermineDownloadUrlMethod::fromDownloadUrlMethods($piePackage, $downloadUrlMethods, $downloadMethodFailures);
                }

                $selectedDownloadUrlMethod->writeToComposerPackage($composerPackage);
                $this->io->write('<info>Selected download URL method: ' . $selectedDownloadUrlMethod->value . '</info>', verbosity: IOInterface::VERBOSE);
            },
        );
    }
}
