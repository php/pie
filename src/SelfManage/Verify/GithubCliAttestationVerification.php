<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Composer\IO\IOInterface;
use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\Util\Emoji;
use Php\Pie\Util\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;

use function implode;
use function sprintf;
use function str_starts_with;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class GithubCliAttestationVerification implements VerifyPiePhar
{
    private const GH_CLI_NAME             = 'gh';
    private const GH_ATTESTATION_COMMAND  = 'attestation';
    private const GH_VERIFICATION_TIMEOUT = 30;

    public function __construct(private readonly ExecutableFinder $executableFinder)
    {
    }

    public function verify(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename, IOInterface $io): void
    {
        $gh = $this->executableFinder->find(self::GH_CLI_NAME);

        if ($gh === null) {
            throw GithubCliNotAvailable::fromExpectedGhToolName(self::GH_CLI_NAME);
        }

        // Try to use `gh attestation --help` to ensure it is not an old `gh` cli version
        try {
            Process::run([$gh, self::GH_ATTESTATION_COMMAND, '--help'], null, self::GH_VERIFICATION_TIMEOUT);
        } catch (ProcessFailedException $attestationCommandCheck) {
            if (str_starts_with($attestationCommandCheck->getProcess()->getErrorOutput(), sprintf('unknown command "%s" for "%s"', self::GH_ATTESTATION_COMMAND, self::GH_CLI_NAME))) {
                throw GithubCliNotAvailable::withMissingAttestationCommand(self::GH_CLI_NAME);
            }

            throw $attestationCommandCheck;
        }

        $verificationCommand = [
            $gh,
            self::GH_ATTESTATION_COMMAND,
            'verify',
            '--owner=php',
            $pharFilename->filePath,
        ];

        $io->write(
            'Verifying using: ' . implode(' ', $verificationCommand),
            verbosity: IOInterface::VERBOSE,
        );

        try {
            Process::run($verificationCommand, null, self::GH_VERIFICATION_TIMEOUT);
        } catch (ProcessFailedException $processFailedException) {
            throw FailedToVerifyRelease::fromGhCliFailure($releaseMetadata, $processFailedException);
        }

        $io->write(sprintf('<info>%s Verified the new PIE version</info>', Emoji::GREEN_CHECKMARK));
    }
}
