<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Composer\Util\AuthHelper;
use Composer\Util\HttpDownloader;
use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Symfony\Component\Console\Output\OutputInterface;
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
    public static function factory(
        string $githubApiBaseUrl,
        HttpDownloader $httpDownloader,
        AuthHelper $authHelper,
    ): self {
        return new VerifyPieReleaseUsingAttestation(
            new GithubCliAttestationVerification($githubApiBaseUrl, $httpDownloader, $authHelper, new ExecutableFinder()),
            new FallbackVerificationUsingOpenSsl($githubApiBaseUrl, $httpDownloader, $authHelper),
        );
    }

    public function verify(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename, OutputInterface $output): void
    {
        $this->githubCliVerification->verify($releaseMetadata, $pharFilename, $output);

        if (! extension_loaded('openssl')) {
            throw FailedToVerifyRelease::fromNoOpenssl();
        }

        $this->fallbackVerification->verify($releaseMetadata, $pharFilename, $output);
    }
}
