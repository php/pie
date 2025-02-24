<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Installing\Ini\RemoveIniEntry;
use Php\Pie\Installing\Uninstall;
use Symfony\Component\Console\Output\OutputInterface;

use function array_walk;
use function count;
use function reset;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class UninstallProcess
{
    /** @psalm-suppress PossiblyUnusedMethod no direct reference; used in service locator */
    public function __construct(
        private readonly RemoveIniEntry $removeIniEntry,
        private readonly Uninstall $uninstall,
    ) {
    }

    public function __invoke(
        PieComposerRequest $composerRequest,
        CompletePackageInterface $composerPackage,
    ): void {
        $output = $composerRequest->pieOutput;

        $piePackage = Package::fromComposerCompletePackage($composerPackage);

        $affectedIniFiles = ($this->removeIniEntry)($piePackage, $composerRequest->targetPlatform);

        if (count($affectedIniFiles) === 1) {
            $output->writeln(
                sprintf('INI file "%s" was updated to remove the extension.', reset($affectedIniFiles)),
                OutputInterface::VERBOSITY_VERBOSE,
            );
        } elseif (count($affectedIniFiles) === 0) {
            $output->writeln(
                'No INI files were updated to remove the extension.',
                OutputInterface::VERBOSITY_VERBOSE,
            );
        } else {
            $output->writeln(
                'The following INI files were updated to remove the extnesion:',
                OutputInterface::VERBOSITY_VERBOSE,
            );
            array_walk($affectedIniFiles, static fn (string $ini) => $output->writeln(' - ' . $ini));
        }

        $output->writeln(sprintf('👋 <info>Removed extension:</info> %s', ($this->uninstall)($piePackage)->filePath));
    }
}
