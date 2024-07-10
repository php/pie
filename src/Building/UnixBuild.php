<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use Php\Pie\Downloading\DownloadedPackage;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function count;
use function file_exists;
use function implode;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class UnixBuild implements Build
{
    /** {@inheritDoc} */
    public function __invoke(
        DownloadedPackage $downloadedPackage,
        array $configureOptions,
        OutputInterface $output,
    ): void {
        $this->phpize($downloadedPackage);
        $output->writeln('<info>phpize complete</info>.');

        $this->configure($downloadedPackage, $configureOptions);
        $optionsOutput = count($configureOptions) ? ' with options: ' . implode(' ', $configureOptions) : '.';
        $output->writeln('<info>Configure complete</info>' . $optionsOutput);

        $this->make($downloadedPackage);

        $expectedSoFile = $downloadedPackage->extractedSourcePath . '/modules/' . $downloadedPackage->package->extensionName->name() . '.so';

        if (! file_exists($expectedSoFile)) {
            $output->writeln(sprintf(
                'Build complete, but expected <comment>%s</comment> does not exist - however, this may be normal if this extension outputs the .so file in a different location.',
                $expectedSoFile,
            ));

            return;
        }

        $output->writeln(sprintf(
            '<info>Build complete:</info> %s',
            $expectedSoFile,
        ));
    }

    private function phpize(DownloadedPackage $downloadedPackage): void
    {
        (new Process(['phpize'], $downloadedPackage->extractedSourcePath))
            ->mustRun();
    }

    /** @param list<non-empty-string> $configureOptions */
    private function configure(DownloadedPackage $downloadedPackage, array $configureOptions = []): void
    {
        (new Process(['./configure', ...$configureOptions], $downloadedPackage->extractedSourcePath))
            ->mustRun();
    }

    private function make(DownloadedPackage $downloadedPackage): void
    {
        (new Process(['make'], $downloadedPackage->extractedSourcePath))
            ->mustRun();
    }
}
