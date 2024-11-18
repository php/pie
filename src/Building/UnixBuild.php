<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use Php\Pie\BinaryFile;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPhp\PhpizePath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Process;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

use function count;
use function file_exists;
use function implode;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class UnixBuild implements Build
{
    private const PHPIZE_TIMEOUT_SECS    = 60; // 1 minute
    private const CONFIGURE_TIMEOUT_SECS = 120; // 2 minutes
    private const MAKE_TIMEOUT_SECS      = null; // unlimited

    /** {@inheritDoc} */
    public function __invoke(
        DownloadedPackage $downloadedPackage,
        TargetPlatform $targetPlatform,
        array $configureOptions,
        OutputInterface $output,
        PhpizePath|null $phpizePath,
    ): BinaryFile {
        $outputCallback = null;
        if ($output->isVerbose()) {
            /** @var callable(SymfonyProcess::ERR|SymfonyProcess::OUT, string):void $outputCallback */
            $outputCallback = static function (string $type, string $outputMessage) use ($output): void {
                $output->write(sprintf(
                    '%s%s%s',
                    $type === SymfonyProcess::ERR ? '<comment>' : '',
                    $outputMessage,
                    $type === SymfonyProcess::ERR ? '</comment>' : '',
                ));
            };
        }

        $this->phpize(
            $phpizePath ?? PhpizePath::guessFrom($targetPlatform->phpBinaryPath),
            $downloadedPackage,
            $output,
            $outputCallback,
        );

        $output->writeln('<info>phpize complete</info>.');

        $phpConfigPath = $targetPlatform->phpBinaryPath->phpConfigPath();
        if ($phpConfigPath !== null) {
            $configureOptions[] = '--with-php-config=' . $phpConfigPath;
        }

        $this->configure($downloadedPackage, $configureOptions, $output, $outputCallback);

        $optionsOutput = count($configureOptions) ? ' with options: ' . implode(' ', $configureOptions) : '.';
        $output->writeln('<info>Configure complete</info>' . $optionsOutput);

        $this->make($targetPlatform, $downloadedPackage, $output, $outputCallback);

        $expectedSoFile = $downloadedPackage->extractedSourcePath . '/modules/' . $downloadedPackage->package->extensionName->name() . '.so';

        if (! file_exists($expectedSoFile)) {
            throw ExtensionBinaryNotFound::fromExpectedBinary($expectedSoFile);
        }

        $output->writeln(sprintf(
            '<info>Build complete:</info> %s',
            $expectedSoFile,
        ));

        return BinaryFile::fromFileWithSha256Checksum($expectedSoFile);
    }

    /** @param callable(SymfonyProcess::ERR|SymfonyProcess::OUT, string): void|null $outputCallback */
    private function phpize(
        PhpizePath $phpize,
        DownloadedPackage $downloadedPackage,
        OutputInterface $output,
        callable|null $outputCallback,
    ): void {
        $phpizeCommand = [$phpize->phpizeBinaryPath];

        if ($output->isVerbose()) {
            $output->writeln('<comment>Running phpize step using: ' . implode(' ', $phpizeCommand) . '</comment>');
        }

        Process::run(
            $phpizeCommand,
            $downloadedPackage->extractedSourcePath,
            self::PHPIZE_TIMEOUT_SECS,
            $outputCallback,
        );
    }

    /**
     * @param list<non-empty-string>                                               $configureOptions
     * @param callable(SymfonyProcess::ERR|SymfonyProcess::OUT, string): void|null $outputCallback
     */
    private function configure(
        DownloadedPackage $downloadedPackage,
        array $configureOptions,
        OutputInterface $output,
        callable|null $outputCallback,
    ): void {
        $configureCommand = ['./configure', ...$configureOptions];

        if ($output->isVerbose()) {
            $output->writeln('<comment>Running configure step with: ' . implode(' ', $configureCommand) . '</comment>');
        }

        Process::run(
            $configureCommand,
            $downloadedPackage->extractedSourcePath,
            self::CONFIGURE_TIMEOUT_SECS,
            $outputCallback,
        );
    }

    /** @param callable(SymfonyProcess::ERR|SymfonyProcess::OUT, string): void|null $outputCallback */
    private function make(
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        OutputInterface $output,
        callable|null $outputCallback,
    ): void {
        $makeCommand = ['make'];

        if ($targetPlatform->makeParallelJobs === 1) {
            $output->writeln('Running make without parallelization - try providing -jN to PIE where N is the number of cores you have.');
        } else {
            $makeCommand[] = sprintf('-j%d', $targetPlatform->makeParallelJobs);
        }

        if ($output->isVerbose()) {
            $output->writeln('<comment>Running make step with: ' . implode(' ', $makeCommand) . '</comment>');
        }

        Process::run(
            $makeCommand,
            $downloadedPackage->extractedSourcePath,
            self::MAKE_TIMEOUT_SECS,
            $outputCallback,
        );
    }
}
