<?php

declare(strict_types=1);

namespace Php\Pie\Installing;

use Php\Pie\BinaryFile;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\Ini\SetupIniApproach;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class SetupIniFile
{
    /** @psalm-suppress PossiblyUnusedMethod no direct reference; used in service locator */
    public function __construct(private readonly SetupIniApproach $setupIniApproach)
    {
    }

    public function __invoke(
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        BinaryFile $binaryFile,
        OutputInterface $output,
        bool $attemptToSetupIniFile,
    ): void {
        if (
            $attemptToSetupIniFile
            && $this->setupIniApproach->canBeUsed($targetPlatform)
            && $this->setupIniApproach->setup($targetPlatform, $downloadedPackage, $binaryFile, $output)
        ) {
            $output->writeln(sprintf(
                '<info>✅ Extension is enabled and loaded in</info> %s',
                $targetPlatform->phpBinaryPath->phpBinaryPath,
            ));
        } else {
            if (! $attemptToSetupIniFile) {
                $output->writeln('Automatic extension enabling was skipped.', OutputInterface::VERBOSITY_VERBOSE);
            }

            $output->writeln('<comment>⚠️  Extension is not enabled.</comment>');
            $output->writeln(sprintf(
                '<comment>Add "%s=%s" to your php.ini to enable this extension.</comment>',
                $downloadedPackage->package->extensionType === ExtensionType::PhpModule ? 'extension' : 'zend_extension',
                $downloadedPackage->package->extensionName->name(),
            ));
        }
    }
}
