<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\File\BinaryFile;
use Php\Pie\File\Sudo;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Process;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

use function array_unshift;
use function file_exists;
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
        OutputInterface $output,
        bool $attemptToSetupIniFile,
    ): BinaryFile {
        $targetExtensionPath = $targetPlatform->phpBinaryPath->extensionPath();

        $sharedObjectName             = $downloadedPackage->package->extensionName()->name() . '.so';
        $expectedSharedObjectLocation = sprintf(
            '%s/%s',
            $targetExtensionPath,
            $sharedObjectName,
        );

        $makeInstallCommand = ['make', 'install'];

        // If the target directory isn't writable, or a .so file already exists and isn't writable, try to use sudo
        if (
            (
                ! is_writable($targetExtensionPath)
                || (file_exists($expectedSharedObjectLocation) && ! is_writable($expectedSharedObjectLocation))
            )
            && Sudo::exists()
        ) {
            $output->writeln(sprintf(
                '<comment>Cannot write to %s, so using sudo to elevate privileges.</comment>',
                $targetExtensionPath,
            ));
            array_unshift($makeInstallCommand, Sudo::find());
        }

        $makeInstallOutput = Process::run(
            $makeInstallCommand,
            $downloadedPackage->extractedSourcePath,
            self::MAKE_INSTALL_TIMEOUT_SECS,
        );

        if ($output->isVeryVerbose()) {
            $output->writeln($makeInstallOutput);
        }

        if (! file_exists($expectedSharedObjectLocation)) {
            throw new RuntimeException('Install failed, ' . $expectedSharedObjectLocation . ' was not installed.');
        }

        $output->writeln('<info>Install complete:</info> ' . $expectedSharedObjectLocation);

        $binaryFile = BinaryFile::fromFileWithSha256Checksum($expectedSharedObjectLocation);

        ($this->setupIniFile)(
            $targetPlatform,
            $downloadedPackage,
            $binaryFile,
            $output,
            $attemptToSetupIniFile,
        );

        return $binaryFile;
    }
}
