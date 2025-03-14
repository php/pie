<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Composer\Downloader\TransportException;
use Composer\Util\AuthHelper;
use Composer\Util\HttpDownloader;
use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\ReleaseMetadata;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class VerifyPieReleaseUsingAttestation implements VerifyPiePhar
{
    public function __construct(
        private readonly string $githubApiBaseUrl,
        private readonly HttpDownloader $httpDownloader,
        private readonly AuthHelper $authHelper,
    ) {
    }

    public function verify(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename): void
    {
        throw new FailedToVerifyRelease('todo');

        // @todo prefer this $this->verifyUsingGhCli();
        $this->verifyUsingOpenSSL($releaseMetadata, $pharFilename);
    }

    private function verifyUsingGhCli(): void
    {
        // @todo verify using `gh attestation verify` etc
    }

    private function verifyUsingOpenSSL(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename): void
    {
        // @todo require openssl

        $attestations = $this->downloadAttestations($releaseMetadata, $pharFilename);

        /**
         * @link https://github.com/cli/cli/blob/234d2effd545fb9d72ea77aa648caa499aecaa6e/pkg/cmd/attestation/verify/verify.go#L225-L256
         *
         * @todo verify the signature against the certificate
         */
    }

    private function downloadAttestations(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename): array
    {
        $attestationUrl = $this->githubApiBaseUrl . '/orgs/php/attestations/sha256:' . $pharFilename->checksum;

        try {
            return $this->httpDownloader->get(
                $attestationUrl,
                [
                    'retry-auth-failure' => false,
                    'http' => [
                        'method' => 'GET',
                        'header' => $this->authHelper->addAuthenticationHeader([], $this->githubApiBaseUrl, $attestationUrl),
                    ],
                ],
            )->decodeJson();
        } catch (TransportException $transportException) {
            if ($transportException->getStatusCode() === 404) {
                throw FailedToVerifyRelease::fromMissingAttestation($releaseMetadata, $pharFilename);
            }

            throw $transportException;
        }
    }
}
