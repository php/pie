<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\BinaryFile;
use Php\Pie\ComposerIntegration\PieInstalledJsonMetadataKeys;
use Php\Pie\DependencyResolver\Package;

use function array_key_exists;
use function unlink;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class UninstallUsingUnlink implements Uninstall
{
    public function __invoke(Package $package): void
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

        unlink($expectedBinaryFile->filePath);
        // @todo verify the unlink worked etc, maybe permissions failed
    }
}
