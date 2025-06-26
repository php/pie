<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\Util\Emoji;
use Php\Pie\Util\Process;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;

use function implode;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class GithubCliAttestationVerification implements VerifyPiePhar
{
    private const GH_CLI_NAME             = 'gh';
    private const GH_VERIFICATION_TIMEOUT = 30;

    public function __construct(private readonly ExecutableFinder $executableFinder)
    {
    }

    public function verify(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename, OutputInterface $output): void
    {
        $gh = $this->executableFinder->find(self::GH_CLI_NAME);

        if ($gh === null) {
            throw GithubCliNotAvailable::fromExpectedGhToolName(self::GH_CLI_NAME);
        }

        $verificationCommand = [
            $gh,
            'attestation',
            'verify',
            '--owner=php',
            $pharFilename->filePath,
        ];

        $output->writeln('Verifying using: ' . implode(' ', $verificationCommand), OutputInterface::VERBOSITY_VERBOSE);

        try {
            Process::run($verificationCommand, null, self::GH_VERIFICATION_TIMEOUT);
        } catch (ProcessFailedException $processFailedException) {
            throw FailedToVerifyRelease::fromGhCliFailure($releaseMetadata, $processFailedException);
        }

        $output->writeln(sprintf('<info>%s Verified the new PIE version</info>', Emoji::GREEN_CHECKMARK));
    }
}
