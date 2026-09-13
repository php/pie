<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Composer\Config;
use Composer\IO\IOInterface;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\FetchPieRelease;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Symfony\Component\Process\ExecutableFinder;

use function extension_loaded;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class VerifyPieReleaseUsingAttestation implements VerifyPiePhar
{
    public function __construct(
        private readonly GithubCliAttestationVerification $githubCliVerification,
        private readonly FallbackVerificationUsingOpenSsl $fallbackVerification,
    ) {
    }

    /** @param non-empty-string $githubApiBaseUrl */
    public static function factory(FetchPieRelease $fetchPieRelease, QuieterConsoleIO $io, Config $config, string $githubApiBaseUrl): self
    {
        return new VerifyPieReleaseUsingAttestation(
            new GithubCliAttestationVerification(new ExecutableFinder(), $fetchPieRelease),
            FallbackVerificationUsingOpenSsl::factory($fetchPieRelease, $io, $config, $githubApiBaseUrl),
        );
    }

    public function verify(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename, IOInterface $io): void
    {
        try {
            $this->githubCliVerification->verify($releaseMetadata, $pharFilename, $io);
        } catch (GithubCliNotAvailable $githubCliNotAvailable) {
            $io->writeError($githubCliNotAvailable->getMessage(), verbosity: IOInterface::VERBOSE);

            if (! extension_loaded('openssl')) {
                throw FailedToVerifyRelease::fromNoOpenssl();
            }

            $this->fallbackVerification->verify($releaseMetadata, $pharFilename, $io);
        }
    }
}
