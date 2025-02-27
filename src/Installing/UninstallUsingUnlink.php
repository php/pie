<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\ComposerIntegration\PieInstalledJsonMetadataKeys;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\File\BinaryFile;
use Php\Pie\Util\Process;

use function array_key_exists;
use function file_exists;
use function is_writable;
use function unlink;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class UninstallUsingUnlink implements Uninstall
{
    public function __invoke(Package $package): BinaryFile
    {
        $pieMetadata = PieInstalledJsonMetadataKeys::pieMetadataFromComposerPackage($package->composerPackage());

        if (
            ! array_key_exists(PieInstalledJsonMetadataKeys::InstalledBinary->value, $pieMetadata)
            || ! array_key_exists(PieInstalledJsonMetadataKeys::BinaryChecksum->value, $pieMetadata)
        ) {
            throw PackageMetadataMissing::duringUninstall(
                $package,
                $pieMetadata,
                [
                    PieInstalledJsonMetadataKeys::InstalledBinary->value,
                    PieInstalledJsonMetadataKeys::BinaryChecksum->value,
                ],
            );
        }

        $expectedBinaryFile = new BinaryFile(
            $pieMetadata[PieInstalledJsonMetadataKeys::InstalledBinary->value],
            $pieMetadata[PieInstalledJsonMetadataKeys::BinaryChecksum->value],
        );

        $expectedBinaryFile->verify();

        // If the target directory isn't writable, or a .so file already exists and isn't writable, try to use sudo
        if (file_exists($expectedBinaryFile->filePath) && ! is_writable($expectedBinaryFile->filePath)) {
            Process::run(['sudo', 'rm', $expectedBinaryFile->filePath]);

            // Removal worked, bail out
            if (! file_exists($expectedBinaryFile->filePath)) {
                return $expectedBinaryFile;
            }
        }

        if (file_exists($expectedBinaryFile->filePath) && ! unlink($expectedBinaryFile->filePath)) {
            throw FailedToRemoveExtension::withFilename($expectedBinaryFile);
        }

        return $expectedBinaryFile;
    }
}
