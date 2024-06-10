<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use Php\Pie\Downloading\DownloadedPackage;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function count;
use function implode;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class UnixBuild implements Build
{
    public function __invoke(DownloadedPackage $downloadedPackage, OutputInterface $output): void
    {
        $this->phpize($downloadedPackage);
        $output->writeln('<info>phpize complete</info>');

        // @todo options
        $configureOptions = [];
        $this->configure($downloadedPackage, $configureOptions);
        $optionsOutput = count($configureOptions) ? ' with options: ' . implode(' ', $configureOptions) : '.';
        $output->writeln('<info>Configure complete</info>' . $optionsOutput);

        $this->make($downloadedPackage);
        $output->writeln('<info>Build complete.</info>');
    }

    private function phpize(DownloadedPackage $downloadedPackage): void
    {
        (new Process(['phpize'], $downloadedPackage->extractedSourcePath))
            ->mustRun()
            ->getOutput();
    }

    /** @param list<non-empty-string> $configureOptions */
    private function configure(DownloadedPackage $downloadedPackage, array $configureOptions = []): void
    {
        (new Process(['./configure', ...$configureOptions], $downloadedPackage->extractedSourcePath))
            ->mustRun()
            ->getOutput();
    }

    private function make(DownloadedPackage $downloadedPackage): void
    {
        (new Process(['make'], $downloadedPackage->extractedSourcePath))
            ->mustRun()
            ->getOutput();
    }
}
