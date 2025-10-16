<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use Composer\IO\IOInterface;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\File\BinaryFile;
use Php\Pie\Platform\TargetPhp\PhpizePath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\WindowsExtensionAssetName;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class WindowsBuild implements Build
{
    /** {@inheritDoc} */
    public function __invoke(
        DownloadedPackage $downloadedPackage,
        TargetPlatform $targetPlatform,
        array $configureOptions,
        IOInterface $io,
        PhpizePath|null $phpizePath,
    ): BinaryFile {
        $prebuiltDll = WindowsExtensionAssetName::determineDllName($targetPlatform, $downloadedPackage);

        $io->write(sprintf(
            '<info>Nothing to do on Windows, prebuilt DLL found:</info> %s',
            $prebuiltDll,
        ));

        return BinaryFile::fromFileWithSha256Checksum($prebuiltDll);
    }
}
