<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;
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
        TargetPlatform $targetPlatform,
        array $configureOptions,
        OutputInterface $output,
    ): void {
        $phpizeOutput = $this->phpize($downloadedPackage);
        if ($output->isVeryVerbose()) {
            $output->writeln($phpizeOutput);
        }
        $output->writeln('<info>phpize complete</info>.');

        $phpConfigPath = $targetPlatform->phpBinaryPath->phpConfigPath();
        if ($phpConfigPath !== null) {
            $configureOptions[] = '--with-php-config=' . $phpConfigPath;
        }

        $configureOutput = $this->configure($downloadedPackage, $configureOptions);
        if ($output->isVeryVerbose()) {
            $output->writeln($configureOutput);
        }
        $optionsOutput = count($configureOptions) ? ' with options: ' . implode(' ', $configureOptions) : '.';
        $output->writeln('<info>Configure complete</info>' . $optionsOutput);

        $makeOutput = $this->make($downloadedPackage);
        if ($output->isVeryVerbose()) {
            $output->writeln($makeOutput);
        }

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

    private function phpize(DownloadedPackage $downloadedPackage): string
    {
        return (new Process(['phpize'], $downloadedPackage->extractedSourcePath))
            ->mustRun()
            ->getOutput();
    }

    /** @param list<non-empty-string> $configureOptions */
    private function configure(DownloadedPackage $downloadedPackage, array $configureOptions = []): string
    {
        return (new Process(['./configure', ...$configureOptions], $downloadedPackage->extractedSourcePath))
            ->mustRun()
            ->getOutput();
    }

    private function make(DownloadedPackage $downloadedPackage): string
    {
        return (new Process(['make'], $downloadedPackage->extractedSourcePath))
            ->mustRun()
            ->getOutput();
    }
}
