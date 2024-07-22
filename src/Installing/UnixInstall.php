<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\TargetPlatform;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function file_exists;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class UnixInstall implements Install
{
    public function __invoke(DownloadedPackage $downloadedPackage, TargetPlatform $targetPlatform, OutputInterface $output): string
    {
        (new Process(['sudo', 'make', 'install'], $downloadedPackage->extractedSourcePath))
            ->mustRun()
            ->getOutput();

        $sharedObjectName             = $downloadedPackage->package->extensionName->name() . '.so';
        $expectedSharedObjectLocation = sprintf(
            '%s/%s',
            $targetPlatform->phpBinaryPath->extensionPath(),
            $sharedObjectName,
        );

        if (! file_exists($expectedSharedObjectLocation)) {
            throw new RuntimeException('Install failed, ' . $expectedSharedObjectLocation . ' was not installed.');
        }

        $output->writeln('<info>Install complete:</info> ' . $expectedSharedObjectLocation);

        /**
         * @link https://github.com/php/pie/issues/20
         *
         * @todo this should be improved in future to try to automatically set up the ext
         */
        $output->writeln(sprintf(
            '<comment>You must now add "%s=%s" to your php.ini</comment>',
            $downloadedPackage->package->extensionType === ExtensionType::PhpModule ? 'extension' : 'zend_extension',
            $downloadedPackage->package->extensionName->name(),
        ));

        return $expectedSharedObjectLocation;
    }
}
