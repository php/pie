<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use Php\Pie\BinaryFile;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPhp\PhpizePath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\WindowsExtensionAssetName;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class WindowsBuild implements Build
{
    /** {@inheritDoc} */
    public function __invoke(
        DownloadedPackage $downloadedPackage,
        TargetPlatform $targetPlatform,
        array $configureOptions,
        OutputInterface $output,
        PhpizePath|null $phpizePath,
        bool $dryRun,
    ): BinaryFile {
        $prebuiltDll = WindowsExtensionAssetName::determineDllName($targetPlatform, $downloadedPackage);

        $output->writeln(sprintf(
            '<info>Nothing to do on Windows, prebuilt DLL found:</info> %s',
            $prebuiltDll,
        ));

        return BinaryFile::fromFileWithSha256Checksum($prebuiltDll);
    }
}
