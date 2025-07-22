<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use Php\Pie\ComposerIntegration\BundledPhpExtensionsRepository;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\File\BinaryFile;
use Php\Pie\Platform\TargetPhp\PhpizePath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Process;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

use function count;
use function file_exists;
use function implode;
use function rename;
use function sprintf;

use const DIRECTORY_SEPARATOR;

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

        $phpizePath ??= PhpizePath::guessFrom($targetPlatform->phpBinaryPath);

        /**
         * Call a cleanup first; most of the time, we expect to be changing a
         * version (e.g. upgrade, downgrade), in which case the source is
         * already clean anyway; however, sometimes we want to rebuild the
         * current ext, so this will perform a clean first
         */
        $this->cleanup($phpizePath, $downloadedPackage, $output, $outputCallback);

        $this->phpize(
            $phpizePath,
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

        $expectedSoFile = $downloadedPackage->extractedSourcePath . '/modules/' . $downloadedPackage->package->extensionName()->name() . '.so';

        if (! file_exists($expectedSoFile)) {
            throw ExtensionBinaryNotFound::fromExpectedBinary($expectedSoFile);
        }

        $output->writeln(sprintf(
            '<info>Build complete:</info> %s',
            $expectedSoFile,
        ));

        return BinaryFile::fromFileWithSha256Checksum($expectedSoFile);
    }

    private function renamesToConfigM4(DownloadedPackage $downloadedPackage, OutputInterface $output): void
    {
        $configM4 = $downloadedPackage->extractedSourcePath . DIRECTORY_SEPARATOR . 'config.m4';
        if (file_exists($configM4)) {
            return;
        }

        $output->writeln('config.m4 does not exist; checking alternatives', OutputInterface::VERBOSITY_VERY_VERBOSE);
        foreach (['config0.m4', 'config9.m4'] as $alternateConfigM4) {
            $fullPathToAlternate = $downloadedPackage->extractedSourcePath . DIRECTORY_SEPARATOR . $alternateConfigM4;
            if (file_exists($fullPathToAlternate)) {
                $output->writeln(sprintf('Renaming %s to config.m4', $alternateConfigM4), OutputInterface::VERBOSITY_VERY_VERBOSE);
                rename($fullPathToAlternate, $configM4);

                return;
            }
        }
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

        $this->renamesToConfigM4($downloadedPackage, $output);

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

        $makeCommand = BundledPhpExtensionsRepository::augmentMakeCommandForPhpBundledExtensions(
            $makeCommand,
            $downloadedPackage,
        );

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

    /** @param callable(SymfonyProcess::ERR|SymfonyProcess::OUT, string): void|null $outputCallback */
    private function cleanup(
        PhpizePath $phpize,
        DownloadedPackage $downloadedPackage,
        OutputInterface $output,
        callable|null $outputCallback,
    ): void {
        /**
         * A basic, but fallible check to see if we should clean first. This
         * should work "most" of the time, unless someone has removed the
         * configure script manually...
         */
        if (! file_exists($downloadedPackage->extractedSourcePath . '/configure')) {
            if ($output->isVerbose()) {
                $output->writeln('<comment>Skipping phpize --clean, configure does not exist</comment>');
            }

            return;
        }

        $phpizeCleanCommand = [$phpize->phpizeBinaryPath, '--clean'];

        if ($output->isVerbose()) {
            $output->writeln('<comment>Running phpize --clean step using: ' . implode(' ', $phpizeCleanCommand) . '</comment>');
        }

        Process::run(
            $phpizeCleanCommand,
            $downloadedPackage->extractedSourcePath,
            self::PHPIZE_TIMEOUT_SECS,
            $outputCallback,
        );

        $output->writeln('<info>Build files cleaned up.</info>');
    }
}
