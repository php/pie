<?php

declare(strict_types=1);

namespace Php\Pie\Building;

use Composer\IO\IOInterface;
use Php\Pie\ComposerIntegration\BundledPhpExtensionsRepository;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\File\BinaryFile;
use Php\Pie\Platform\TargetPhp\PhpizePath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Process;
use Php\Pie\Util\ProcessFailedWithLimitedOutput;
use Symfony\Component\Process\Exception\ProcessFailedException;
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
        IOInterface $io,
        PhpizePath|null $phpizePath,
    ): BinaryFile {
        $outputCallback = null;
        if ($io->isVerbose()) {
            $outputCallback = static function (string $type, string $outputMessage) use ($io): void {
                $io->write(sprintf(
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
        $this->cleanup($phpizePath, $downloadedPackage, $io, $outputCallback);

        $this->phpize(
            $phpizePath,
            $downloadedPackage,
            $io,
            $outputCallback,
        );

        $io->write('<info>phpize complete</info>.');

        $phpConfigPath = $targetPlatform->phpBinaryPath->phpConfigPath();
        if ($phpConfigPath !== null) {
            $configureOptions[] = '--with-php-config=' . $phpConfigPath;
        }

        $this->configure($downloadedPackage, $configureOptions, $io, $outputCallback);

        $optionsOutput = count($configureOptions) ? ' with options: ' . implode(' ', $configureOptions) : '.';
        $io->write('<info>Configure complete</info>' . $optionsOutput);

        try {
            $this->make($targetPlatform, $downloadedPackage, $io, $outputCallback);
        } catch (ProcessFailedException $p) {
            throw ProcessFailedWithLimitedOutput::fromProcessFailedException($p);
        }

        $expectedSoFile = $downloadedPackage->extractedSourcePath . '/modules/' . $downloadedPackage->package->extensionName()->name() . '.so';

        if (! file_exists($expectedSoFile)) {
            throw ExtensionBinaryNotFound::fromExpectedBinary($expectedSoFile);
        }

        $io->write(sprintf(
            '<info>Build complete:</info> %s',
            $expectedSoFile,
        ));

        return BinaryFile::fromFileWithSha256Checksum($expectedSoFile);
    }

    private function renamesToConfigM4(DownloadedPackage $downloadedPackage, IOInterface $io): void
    {
        $configM4 = $downloadedPackage->extractedSourcePath . DIRECTORY_SEPARATOR . 'config.m4';
        if (file_exists($configM4)) {
            return;
        }

        $io->write('config.m4 does not exist; checking alternatives', verbosity: IOInterface::VERY_VERBOSE);
        foreach (['config0.m4', 'config9.m4'] as $alternateConfigM4) {
            $fullPathToAlternate = $downloadedPackage->extractedSourcePath . DIRECTORY_SEPARATOR . $alternateConfigM4;
            if (file_exists($fullPathToAlternate)) {
                $io->write(sprintf('Renaming %s to config.m4', $alternateConfigM4), verbosity: IOInterface::VERY_VERBOSE);
                rename($fullPathToAlternate, $configM4);

                return;
            }
        }
    }

    /** @param callable(SymfonyProcess::ERR|SymfonyProcess::OUT, string): void|null $outputCallback */
    private function phpize(
        PhpizePath $phpize,
        DownloadedPackage $downloadedPackage,
        IOInterface $io,
        callable|null $outputCallback,
    ): void {
        $phpizeCommand = [$phpize->phpizeBinaryPath];

        $io->write(
            '<comment>Running phpize step using: ' . implode(' ', $phpizeCommand) . '</comment>',
            verbosity: IOInterface::VERBOSE,
        );

        $this->renamesToConfigM4($downloadedPackage, $io);

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
        IOInterface $io,
        callable|null $outputCallback,
    ): void {
        $configureCommand = ['./configure', ...$configureOptions];

        $io->write(
            '<comment>Running configure step with: ' . implode(' ', $configureCommand) . '</comment>',
            verbosity: IOInterface::VERBOSE,
        );

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
        IOInterface $io,
        callable|null $outputCallback,
    ): void {
        $makeCommand = ['make'];

        if ($targetPlatform->makeParallelJobs === 1) {
            $io->write('Running make without parallelization - try providing -jN to PIE where N is the number of cores you have.');
        } else {
            $makeCommand[] = sprintf('-j%d', $targetPlatform->makeParallelJobs);
        }

        $makeCommand = BundledPhpExtensionsRepository::augmentMakeCommandForPhpBundledExtensions(
            $makeCommand,
            $downloadedPackage,
        );

        $io->write(
            '<comment>Running make step with: ' . implode(' ', $makeCommand) . '</comment>',
            verbosity: IOInterface::VERBOSE,
        );

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
        IOInterface $io,
        callable|null $outputCallback,
    ): void {
        /**
         * A basic, but fallible check to see if we should clean first. This
         * should work "most" of the time, unless someone has removed the
         * configure script manually...
         */
        if (! file_exists($downloadedPackage->extractedSourcePath . '/configure')) {
            $io->write(
                '<comment>Skipping phpize --clean, configure does not exist</comment>',
                verbosity: IOInterface::VERBOSE,
            );

            return;
        }

        $phpizeCleanCommand = [$phpize->phpizeBinaryPath, '--clean'];

        $io->write(
            '<comment>Running phpize --clean step using: ' . implode(' ', $phpizeCleanCommand) . '</comment>',
            verbosity: IOInterface::VERBOSE,
        );

        Process::run(
            $phpizeCleanCommand,
            $downloadedPackage->extractedSourcePath,
            self::PHPIZE_TIMEOUT_SECS,
            $outputCallback,
        );

        $io->write('<info>Build files cleaned up.</info>');
    }
}
