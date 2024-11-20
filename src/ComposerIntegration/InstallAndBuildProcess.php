<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Package\CompletePackageInterface;
use Composer\PartialComposer;
use Php\Pie\Building\Build;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Installing\Install;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class InstallAndBuildProcess
{
    /** @psalm-suppress PossiblyUnusedMethod no direct reference; used in service locator */
    public function __construct(
        private readonly Build $pieBuild,
        private readonly Install $pieInstall,
        private readonly InstalledJsonMetadata $installedJsonMetadata,
    ) {
    }

    public function __invoke(
        PartialComposer $composer,
        PieComposerRequest $composerRequest,
        CompletePackageInterface $composerPackage,
        string $installPath,
    ): void {
        $output = $composerRequest->pieOutput;

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            Package::fromComposerCompletePackage($composerPackage),
            $installPath,
        );

        $output->writeln(sprintf(
            '<info>Extracted %s source to:</info> %s',
            $downloadedPackage->package->prettyNameAndVersion(),
            $downloadedPackage->extractedSourcePath,
        ));

        $this->installedJsonMetadata->addDownloadMetadata(
            $composer,
            $composerRequest,
            $composerPackage,
        );

        if ($composerRequest->operation->shouldBuild()) {
            $builtBinaryFile = ($this->pieBuild)(
                $downloadedPackage,
                $composerRequest->targetPlatform,
                $composerRequest->configureOptions,
                $output,
                $composerRequest->phpizePath,
                $composerRequest->dryRun,
            );

            $this->installedJsonMetadata->addBuildMetadata(
                $composer,
                $composerRequest,
                $composerPackage,
                $builtBinaryFile,
            );
        }

        if (! $composerRequest->operation->shouldInstall()) {
            return;
        }

        $this->installedJsonMetadata->addInstallMetadata(
            $composer,
            $composerPackage,
            ($this->pieInstall)(
                $downloadedPackage,
                $composerRequest->targetPlatform,
                $output,
                $composerRequest->dryRun,
            ),
        );
    }
}
