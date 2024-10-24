<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPhp\PhpizePath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Process;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function file_exists;
use function implode;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class UnixBuild implements Build
{
    private const PHPIZE_TIMEOUT_SECS    = 60; // 1 minute
    private const CONFIGURE_TIMEOUT_SECS = 120; // 2 minutes
    private const MAKE_TIMEOUT_SECS      = 600; // 10 minutes

    /** {@inheritDoc} */
    public function __invoke(
        DownloadedPackage $downloadedPackage,
        TargetPlatform $targetPlatform,
        array $configureOptions,
        OutputInterface $output,
    ): void {
        $phpizeOutput = $this->phpize(
            PhpizePath::guessFrom($targetPlatform->phpBinaryPath),
            $downloadedPackage,
        );
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

        $makeOutput = $this->make($targetPlatform, $downloadedPackage);
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

    private function phpize(PhpizePath $phpize, DownloadedPackage $downloadedPackage): string
    {
        return Process::run(
            [$phpize->phpizeBinaryPath],
            $downloadedPackage->extractedSourcePath,
            self::PHPIZE_TIMEOUT_SECS,
        );
    }

    /** @param list<non-empty-string> $configureOptions */
    private function configure(DownloadedPackage $downloadedPackage, array $configureOptions = []): string
    {
        return Process::run(
            ['./configure', ...$configureOptions],
            $downloadedPackage->extractedSourcePath,
            self::CONFIGURE_TIMEOUT_SECS,
        );
    }

    private function make(TargetPlatform $targetPlatform, DownloadedPackage $downloadedPackage): string
    {
        return Process::run(
            ['make', '--jobs', $targetPlatform->makeParallelJobs],
            $downloadedPackage->extractedSourcePath,
            self::MAKE_TIMEOUT_SECS,
        );
    }
}
