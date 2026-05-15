<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\ComposerIntegration\PieInstalledJsonMetadataKeys;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\File\BinaryFile;
use Php\Pie\File\FailedToUnlinkFile;
use Php\Pie\File\Sudo;
use Php\Pie\File\SudoUnlink;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Process;
use RuntimeException;

use function array_key_exists;
use function file_exists;
use function is_writable;
use function sprintf;

use const DIRECTORY_SEPARATOR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class UninstallUsingUnlink implements Uninstall
{
    public function __invoke(TargetPlatform $targetPlatform, Package $package): BinaryFile
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

        // Sanity check the extension metadata points to the correct expected location
        $extensionPathByConvention = $targetPlatform->phpBinaryPath->extensionPath() . DIRECTORY_SEPARATOR . $package->extensionName()->name() . ($targetPlatform->operatingSystem === OperatingSystem::Windows ? '.dll' : '.so');
        if ($extensionPathByConvention !== $pieMetadata[PieInstalledJsonMetadataKeys::InstalledBinary->value]) {
            throw new RuntimeException(sprintf(
                'Stored metadata path "%s" did not match expected path "%s"',
                $pieMetadata[PieInstalledJsonMetadataKeys::InstalledBinary->value],
                $extensionPathByConvention,
            ));
        }

        $expectedBinaryFile = new BinaryFile(
            $pieMetadata[PieInstalledJsonMetadataKeys::InstalledBinary->value],
            $pieMetadata[PieInstalledJsonMetadataKeys::BinaryChecksum->value],
        );

        $expectedBinaryFile->verify();

        // If the target directory isn't writable, or a .so file already exists and isn't writable, try to use sudo
        if (file_exists($expectedBinaryFile->filePath) && ! is_writable($expectedBinaryFile->filePath) && Sudo::exists()) {
            Process::run([Sudo::find(), 'rm', $expectedBinaryFile->filePath], timeout: Process::SHORT_TIMEOUT);

            // Removal worked, bail out
            if (! file_exists($expectedBinaryFile->filePath)) {
                return $expectedBinaryFile;
            }
        }

        try {
            SudoUnlink::singleFile($expectedBinaryFile->filePath);
        } catch (FailedToUnlinkFile $failedToUnlinkFile) {
            throw FailedToRemoveExtension::withFilename($expectedBinaryFile, $failedToUnlinkFile);
        }

        return $expectedBinaryFile;
    }
}
