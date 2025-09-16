<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ExecutableFinder;
use ThePhpFoundation\Attestation\Verification\VerifyAttestationWithOpenSsl;

use function extension_loaded;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class VerifyPieReleaseUsingAttestation implements VerifyPiePhar
{
    public function __construct(
        private readonly GithubCliAttestationVerification $githubCliVerification,
        private readonly FallbackVerificationUsingOpenSsl $fallbackVerification,
    ) {
    }

    public static function factory(): self
    {
        return new VerifyPieReleaseUsingAttestation(
            new GithubCliAttestationVerification(new ExecutableFinder()),
            new FallbackVerificationUsingOpenSsl(VerifyAttestationWithOpenSsl::factory()),
        );
    }

    public function verify(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename, OutputInterface $output): void
    {
        try {
            $this->githubCliVerification->verify($releaseMetadata, $pharFilename, $output);
        } catch (GithubCliNotAvailable $githubCliNotAvailable) {
            $output->writeln($githubCliNotAvailable->getMessage(), OutputInterface::VERBOSITY_VERBOSE);

            if (! extension_loaded('openssl')) {
                throw FailedToVerifyRelease::fromNoOpenssl();
            }

            $this->fallbackVerification->verify($releaseMetadata, $pharFilename, $output);
        }
    }
}
