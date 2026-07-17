<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Composer\IO\IOInterface;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\Util\Emoji;
use Throwable;

use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class RecoverFromFailedVerification
{
    /**
     * @return bool true if the caller should continue the update WITHOUT
     *               verification (user opted in, at their own risk); false
     *               if the caller should abort.
     */
    public function __invoke(IOInterface $io, ReleaseMetadata $releaseMetadata, Throwable $verificationFailure): bool
    {
        $io->writeError(sprintf(
            '<error>%s Failed to verify the pie.phar release %s: %s</error>',
            Emoji::CROSS,
            $releaseMetadata->tag,
            $verificationFailure->getMessage(),
        ));
        $io->writeError('<comment>This means I could not verify that the PHAR we tried to update to was authentic.</comment>');
        $io->writeError(sprintf(
            '<comment>You can manually download release %s yourself from: %s</comment>',
            $releaseMetadata->tag,
            $releaseMetadata->downloadUrl,
        ));

        if (! $io->isInteractive()) {
            $io->writeError(sprintf('<warning>%s You are not running in interactive mode, so I am aborting the self-update.</warning>', Emoji::WARNING));

            return false;
        }

        if (! $io->askConfirmation('<question>Would you like to continue the update <highlight>WITHOUT verification, at your own risk</highlight>? [y/N]</question>', false)) {
            $io->writeError('<comment>Ok, aborting the self-update.</comment>');

            return false;
        }

        $io->writeError(sprintf('<warning>%s Continuing the self-update WITHOUT verifying the release authenticity. This is at your own risk.</warning>', Emoji::WARNING));

        return true;
    }
}
