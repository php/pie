<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Package\CompleteAliasPackage;
use Composer\Package\CompletePackageInterface;
use Composer\PartialComposer;
use Php\Pie\File\BinaryFile;
use Webmozart\Assert\Assert;

use function array_merge;
use function implode;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class AddInstalledJsonMetadata
{
    public function addDownloadMetadata(
        PartialComposer $composer,
        PieComposerRequest $composerRequest,
        CompletePackageInterface $composerPackage,
    ): void {
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            InstalledJsonMetadata::KEY_TARGET_PLATFORM_PHP_PATH,
            $composerRequest->targetPlatform->phpBinaryPath->phpBinaryPath,
        );
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            InstalledJsonMetadata::KEY_TARGET_PLATFORM_PHP_CONFIG_PATH,
            $composerRequest->targetPlatform->phpBinaryPath->phpConfigPath(),
        );
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            InstalledJsonMetadata::KEY_TARGET_PLATFORM_PHP_VERSION,
            $composerRequest->targetPlatform->phpBinaryPath->version(),
        );
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            InstalledJsonMetadata::KEY_TARGET_PLATFORM_PHP_THREAD_SAFETY,
            $composerRequest->targetPlatform->threadSafety->name,
        );
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            InstalledJsonMetadata::KEY_TARGET_PLATFORM_PHP_WINDOWS_COMPILER,
            $composerRequest->targetPlatform->windowsCompiler?->name,
        );
        $this->addPieMetadata(
            $composer,
            $composerPackage,
            InstalledJsonMetadata::KEY_TARGET_PLATFORM_ARCHITECTURE,
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
            InstalledJsonMetadata::KEY_CONFIGURE_OPTIONS,
            implode(' ', $composerRequest->configureOptionsFor($composerPackage->getName())),
        );

        $this->addPieMetadata(
            $composer,
            $composerPackage,
            InstalledJsonMetadata::KEY_PHPIZE_BINARY,
            $composerRequest->targetPlatform->phpizePath->phpizeBinaryPath ?? null,
        );

        $this->addPieMetadata(
            $composer,
            $composerPackage,
            InstalledJsonMetadata::KEY_BUILT_BINARY,
            $builtBinary->filePath,
        );

        $this->addPieMetadata(
            $composer,
            $composerPackage,
            InstalledJsonMetadata::KEY_BINARY_CHECKSUM,
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
            InstalledJsonMetadata::KEY_INSTALLED_BINARY,
            $installedBinary->filePath,
        );
    }

    /** @param InstalledJsonMetadata::KEY_* $key */
    private function addPieMetadata(
        PartialComposer $composer,
        CompletePackageInterface $composerPackage,
        string $key,
        string|null $value,
    ): void {
        $localRepositoryPackage = $composer
            ->getRepositoryManager()
            ->getLocalRepository()
            ->findPackages($composerPackage->getName())[0];

        if ($localRepositoryPackage instanceof CompleteAliasPackage) {
            $localRepositoryPackage = $localRepositoryPackage->getAliasOf();
        }

        Assert::methodExists($localRepositoryPackage, 'setExtra');

        $localRepositoryPackage->setExtra(array_merge($localRepositoryPackage->getExtra(), [$key => $value]));
    }
}
