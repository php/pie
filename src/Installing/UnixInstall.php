<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Composer\IO\IOInterface;
use Composer\Util\Platform as ComposerPlatform;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Downloading\DownloadUrlMethod;
use Php\Pie\File\BinaryFile;
use Php\Pie\File\Sudo;
use Php\Pie\Platform\MakePath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Process;
use RuntimeException;
use Webmozart\Assert\Assert;

use function array_map;
use function array_merge;
use function file_exists;
use function implode;
use function is_writable;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class UnixInstall implements Install
{
    private const MAKE_INSTALL_TIMEOUT_SECS = 300; // 5 minutes

    public function __construct(private readonly SetupIniFile $setupIniFile)
    {
    }

    public function __invoke(
        DownloadedPackage $downloadedPackage,
        TargetPlatform $targetPlatform,
        BinaryFile|null $builtBinaryFile,
        IOInterface $io,
        bool $attemptToSetupIniFile,
    ): BinaryFile {
        $env         = [];
        $installRoot = (string) ComposerPlatform::getEnv('INSTALL_ROOT');
        if ($installRoot !== '') {
            $io->write(sprintf('<info>Using INSTALL_ROOT=%s</info>', $installRoot));
            $env['INSTALL_ROOT'] = $installRoot;
        }

        $targetExtensionPath = $targetPlatform->phpBinaryPath->extensionPath($installRoot);

        $sharedObjectName             = $downloadedPackage->package->extensionName()->name() . '.so';
        $expectedSharedObjectLocation = sprintf(
            '%s/%s',
            $targetExtensionPath,
            $sharedObjectName,
        );

        $installCommands = [];
        switch (DownloadUrlMethod::fromDownloadedPackage($downloadedPackage)) {
            case DownloadUrlMethod::PrePackagedBinary:
                Assert::notNull($builtBinaryFile);

                if (file_exists($expectedSharedObjectLocation)) {
                    $installCommands[] = [
                        'rm',
                        '-v',
                        $expectedSharedObjectLocation,
                    ];
                }

                $installCommands[] = [
                    'cp',
                    '-v',
                    $builtBinaryFile->filePath,
                    $targetExtensionPath,
                ];
                break;

            default:
                $installCommands[] = [MakePath::guess(), 'install'];
        }

        // If the target directory isn't writable, or a .so file already exists and isn't writable, try to use sudo
        if (
            (
                ! is_writable($targetExtensionPath)
                || (file_exists($expectedSharedObjectLocation) && ! is_writable($expectedSharedObjectLocation))
            )
            && Sudo::exists()
        ) {
            $io->write(sprintf(
                '<comment>Cannot write to %s, so using sudo to elevate privileges.</comment>',
                $targetExtensionPath,
            ));
            $installCommands = array_map(static fn (array $command) => array_merge(['sudo'], $command), $installCommands);
        }

        $io->write(sprintf('<info>Install commands are: %s</info>', implode(', ', array_map(static fn (array $command) => implode(' ', $command), $installCommands))), verbosity: IOInterface::VERY_VERBOSE);

        foreach ($installCommands as $installCommand) {
            $makeInstallOutput = Process::run(
                $installCommand,
                $downloadedPackage->extractedSourcePath,
                self::MAKE_INSTALL_TIMEOUT_SECS,
                env: $env,
            );

            $io->write($makeInstallOutput, verbosity: IOInterface::VERY_VERBOSE);
        }

        if (! file_exists($expectedSharedObjectLocation)) {
            throw new RuntimeException('Install failed, ' . $expectedSharedObjectLocation . ' was not installed.');
        }

        $io->write('<info>Install complete:</info> ' . $expectedSharedObjectLocation);

        $binaryFile = BinaryFile::fromFileWithSha256Checksum($expectedSharedObjectLocation);

        ($this->setupIniFile)(
            $targetPlatform,
            $downloadedPackage,
            $binaryFile,
            $io,
            $attemptToSetupIniFile,
        );

        return $binaryFile;
    }
}
