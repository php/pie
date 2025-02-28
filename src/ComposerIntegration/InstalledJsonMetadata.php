<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Package\CompletePackage;
use Composer\Package\CompletePackageInterface;
use Composer\PartialComposer;
use Php\Pie\ComposerIntegration\PieInstalledJsonMetadataKeys as MetadataKey;
use Php\Pie\File\BinaryFile;
use Webmozart\Assert\Assert;

use function array_merge;
use function implode;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class InstalledJsonMetadata
{
    public function addDownloadMetadata(
        PartialComposer $composer,
        PieComposerRequest $composerRequest,
        CompletePackageInterface $composerPackage,
    ): void {
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            MetadataKey::TargetPlatformPhpPath,
            $composerRequest->targetPlatform->phpBinaryPath->phpBinaryPath,
        );
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            MetadataKey::TargetPlatformPhpConfigPath,
            $composerRequest->targetPlatform->phpBinaryPath->phpConfigPath(),
        );
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            MetadataKey::TargetPlatformPhpVersion,
            $composerRequest->targetPlatform->phpBinaryPath->version(),
        );
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            MetadataKey::TargetPlatformPhpThreadSafety,
            $composerRequest->targetPlatform->threadSafety->name,
        );
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            MetadataKey::TargetPlatformPhpWindowsCompiler,
            $composerRequest->targetPlatform->windowsCompiler?->name,
        );
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            MetadataKey::TargetPlatformArchitecture,
            $composerRequest->targetPlatform->architecture->name,
        );
    }

    public function addBuildMetadata(
        PartialComposer $composer,
        PieComposerRequest $composerRequest,
        CompletePackageInterface $composerPackage,
        BinaryFile $builtBinary,
    ): void {
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            MetadataKey::ConfigureOptions,
            implode(' ', $composerRequest->configureOptions),
        );

        $this->addPieMetadata(
            $composer,
            $composerPackage,
            MetadataKey::PhpizeBinary,
            $composerRequest->phpizePath->phpizeBinaryPath ?? null,
        );

        $this->addPieMetadata(
            $composer,
            $composerPackage,
            MetadataKey::BuiltBinary,
            $builtBinary->filePath,
        );

        $this->addPieMetadata(
            $composer,
            $composerPackage,
            MetadataKey::BinaryChecksum,
            $builtBinary->checksum,
        );
    }

    public function addInstallMetadata(
        PartialComposer $composer,
        CompletePackageInterface $composerPackage,
        BinaryFile $installedBinary,
    ): void {
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            MetadataKey::InstalledBinary,
            $installedBinary->filePath,
        );
    }

    private function addPieMetadata(
        PartialComposer $composer,
        CompletePackageInterface $composerPackage,
        MetadataKey $key,
        string|null $value,
    ): void {
        $localRepositoryPackage = $composer
            ->getRepositoryManager()
            ->getLocalRepository()
            ->findPackages($composerPackage->getName())[0];
        Assert::isInstanceOf($localRepositoryPackage, CompletePackage::class);

        $localRepositoryPackage->setExtra(array_merge($localRepositoryPackage->getExtra(), [$key->value => $value]));
    }
}
