<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Installing\Ini\RemoveIniEntry;
use Php\Pie\Installing\Uninstall;

use function array_walk;
use function count;
use function reset;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class UninstallProcess
{
    public function __construct(
        private readonly RemoveIniEntry $removeIniEntry,
        private readonly Uninstall $uninstall,
    ) {
    }

    public function __invoke(
        PieComposerRequest $composerRequest,
        CompletePackageInterface $composerPackage,
    ): void {
        $io             = $composerRequest->pieOutput;
        $targetPlatform = $composerRequest->targetPlatform;

        $piePackage = Package::fromComposerCompletePackage($composerPackage);

        $status = $piePackage->verifyPackageStatus($composerRequest->targetPlatform);

        if ($status->isInstalled()) {
            $io->write(sprintf('👋 <info>Removed extension:</info> %s', ($this->uninstall)($targetPlatform, $piePackage)->filePath));
        } else {
            $io->writeError(sprintf('<warning>Did not remove extension file:</warning> %s', $status->description()));
        }

        $affectedIniFiles = ($this->removeIniEntry)($piePackage, $composerRequest->targetPlatform, $io);

        if (count($affectedIniFiles) === 1) {
            $io->write(
                sprintf('INI file "%s" was updated to remove the extension.', reset($affectedIniFiles)),
                verbosity: IOInterface::VERBOSE,
            );
        } elseif (count($affectedIniFiles) === 0) {
            $io->write(
                'No INI files were updated to remove the extension.',
                verbosity: IOInterface::VERBOSE,
            );
        } else {
            $io->write(
                'The following INI files were updated to remove the extnesion:',
                verbosity: IOInterface::VERBOSE,
            );
            array_walk($affectedIniFiles, static fn (string $ini) => $io->write(' - ' . $ini));
        }
    }
}
