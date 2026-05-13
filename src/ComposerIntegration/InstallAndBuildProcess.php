<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Package\CompletePackageInterface;
use Composer\PartialComposer;
use Php\Pie\Building\Build;
use Php\Pie\Building\PlaceholderReplacer;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Installing\Install;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class InstallAndBuildProcess
{
    public function __construct(
        private readonly Build $pieBuild,
        private readonly Install $pieInstall,
        private readonly AddInstalledJsonMetadata $addInstalledJsonMetadata,
        private readonly PlaceholderReplacer $placeholderReplacer,
    ) {
    }

    public function __invoke(
        PartialComposer $composer,
        PieComposerRequest $composerRequest,
        CompletePackageInterface $composerPackage,
        string $installPath,
    ): void {
        // @todo determine if we should build, determine if we should install etc
        $io = $composerRequest->pieOutput;

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            Package::fromComposerCompletePackage($composerPackage),
            $installPath,
        );

        $io->write(sprintf(
            '<info>Extracted %s source to:</info> %s',
            $downloadedPackage->package->prettyNameAndVersion(),
            $downloadedPackage->extractedSourcePath,
        ));

        $this->placeholderReplacer->replacePlaceholdersWithPlaceholderReplacements(
            $io,
            $composerRequest->targetPlatform,
            $downloadedPackage,
        );

        $this->addInstalledJsonMetadata->addDownloadMetadata(
            $composer,
            $composerRequest,
            $composerPackage,
        );

        $builtBinaryFile = null;
        if ($composerRequest->operation->shouldBuild()) {
            $builtBinaryFile = ($this->pieBuild)(
                $downloadedPackage,
                $composerRequest->targetPlatform,
                $composerRequest->configureOptions,
                $io,
            );

            $this->addInstalledJsonMetadata->addBuildMetadata(
                $composer,
                $composerRequest,
                $composerPackage,
                $builtBinaryFile,
            );
        }

        if (! $composerRequest->operation->shouldInstall()) {
            return;
        }

        $this->addInstalledJsonMetadata->addInstallMetadata(
            $composer,
            $composerPackage,
            ($this->pieInstall)(
                $downloadedPackage,
                $composerRequest->targetPlatform,
                $builtBinaryFile,
                $io,
                $composerRequest->attemptToSetupIniFile,
            ),
        );
    }
}
