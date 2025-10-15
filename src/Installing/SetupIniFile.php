<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Composer\IO\IOInterface;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionType;
use Php\Pie\File\BinaryFile;
use Php\Pie\Installing\Ini\SetupIniApproach;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Emoji;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class SetupIniFile
{
    public function __construct(private readonly SetupIniApproach $setupIniApproach)
    {
    }

    public function __invoke(
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        BinaryFile $binaryFile,
        IOInterface $io,
        bool $attemptToSetupIniFile,
    ): void {
        if (
            $attemptToSetupIniFile
            && $this->setupIniApproach->canBeUsed($targetPlatform)
            && $this->setupIniApproach->setup($targetPlatform, $downloadedPackage, $binaryFile, $io)
        ) {
            $io->write(sprintf(
                '<info>%s Extension is enabled and loaded in</info> %s',
                Emoji::GREEN_CHECKMARK,
                $targetPlatform->phpBinaryPath->phpBinaryPath,
            ));
        } else {
            if (! $attemptToSetupIniFile) {
                $io->write('Automatic extension enabling was skipped.', verbosity: IOInterface::VERY_VERBOSE);
            }

            $io->write(sprintf('<comment>%s Extension has NOT been automatically enabled.</comment>', Emoji::WARNING));
            $io->write(sprintf(
                '<comment>You must now add "%s=%s" to your php.ini</comment>',
                $downloadedPackage->package->extensionType() === ExtensionType::PhpModule ? 'extension' : 'zend_extension',
                $downloadedPackage->package->extensionName()->name(),
            ));
        }
    }
}
