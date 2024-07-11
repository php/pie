<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class UnixInstall implements Install
{
    public function __invoke(DownloadedPackage $downloadedPackage, TargetPlatform $targetPlatform, OutputInterface $output): void
    {
        (new Process(['sudo', 'make', 'install'], $downloadedPackage->extractedSourcePath))
            ->mustRun()
            ->getOutput();

        $output->writeln('<info>Install complete.</info>');

        // @todo write info on php.ini change to make
    }
}
