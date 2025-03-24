<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Verify;

use Composer\Downloader\TransportException;
use Composer\Util\AuthHelper;
use Composer\Util\HttpDownloader;
use OpenSSLAsymmetricKey;
use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function array_map;
use function count;
use function extension_loaded;
use function is_array;
use function is_string;
use function json_decode;
use function openssl_pkey_get_public;
use function openssl_verify;
use function sprintf;
use function strlen;

use const OPENSSL_ALGO_SHA256;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class FallbackVerificationUsingOpenSsl implements VerifyPiePhar
{
    public function __construct(
        private readonly string $githubApiBaseUrl,
        private readonly HttpDownloader $httpDownloader,
        private readonly AuthHelper $authHelper,
    ) {
    }

    public function verify(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename, OutputInterface $output): void
    {
        $output->writeln(
            'Falling back to basic verification. To use full verification, install the `gh` CLI tool.',
            OutputInterface::VERBOSITY_VERBOSE,
        );

        $attestations = $this->downloadAttestations($releaseMetadata, $pharFilename);

        foreach ($attestations as $attestationIndex => $attestation) {
            /**
             * Useful references. Whilst we don't do the full verification that
             * `gh attestation verify` would (since we don't want to re-invent
             * the wheel), we can do some basic check of the DSSE Envelope.
             * We'll check the payload digest matches our expectation, and
             * verify the signature with the certificate.
             *
             *  - https://github.com/cli/cli/blob/234d2effd545fb9d72ea77aa648caa499aecaa6e/pkg/cmd/attestation/verify/verify.go#L225-L256
             *  - https://docs.sigstore.dev/logging/verify-release/
             *  - https://github.com/secure-systems-lab/dsse/blob/master/protocol.md#protocol
             */
            $this->assertDigestFromAttestationMatchesActual($pharFilename, $attestation);
            $output->writeln('#' . $attestationIndex . ': Payload digest matches downloaded file.', OutputInterface::VERBOSITY_VERBOSE);

            $this->verifyDsseEnvelopeSignature($releaseMetadata, $attestationIndex, $attestation);
            $output->writeln('#' . $attestationIndex . ': DSSE payload signature verified with certificate.', OutputInterface::VERBOSITY_VERBOSE);
        }

        $output->writeln('<info>✅ Verified the new PIE version (using fallback verification)</info>');
    }

    private function verifyDsseEnvelopeSignature(ReleaseMetadata $releaseMetadata, int $attestationIndex, Attestation $attestation): void
    {
        if (! extension_loaded('openssl')) {
            throw FailedToVerifyRelease::fromNoOpenssl();
        }

        $publicKey = openssl_pkey_get_public($attestation->certificate);
        Assert::isInstanceOf($publicKey, OpenSSLAsymmetricKey::class);

        $preAuthenticationEncoding = sprintf(
            'DSSEv1 %d %s %d %s',
            strlen($attestation->dsseEnvelopePayloadType),
            $attestation->dsseEnvelopePayloadType,
            strlen($attestation->dsseEnvelopePayload),
            $attestation->dsseEnvelopePayload,
        );

        if (openssl_verify($preAuthenticationEncoding, $attestation->dsseEnvelopeSignature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw FailedToVerifyRelease::fromSignatureVerificationFailed($attestationIndex, $releaseMetadata);
        }
    }

    private function assertDigestFromAttestationMatchesActual(BinaryFile $pharFilename, Attestation $attestation): void
    {
        /** @var mixed $decodedPayload */
        $decodedPayload = json_decode($attestation->dsseEnvelopePayload, true);

        if (
            ! is_array($decodedPayload)
            || ! array_key_exists('subject', $decodedPayload)
            || ! is_array($decodedPayload['subject'])
            || count($decodedPayload['subject']) !== 1
            || ! array_key_exists(0, $decodedPayload['subject'])
            || ! is_array($decodedPayload['subject'][0])
            || ! array_key_exists('name', $decodedPayload['subject'][0])
            || $decodedPayload['subject'][0]['name'] !== 'pie.phar'
            || ! array_key_exists('digest', $decodedPayload['subject'][0])
            || ! is_array($decodedPayload['subject'][0]['digest'])
            || ! array_key_exists('sha256', $decodedPayload['subject'][0]['digest'])
            || ! is_string($decodedPayload['subject'][0]['digest']['sha256'])
            || $decodedPayload['subject'][0]['digest']['sha256'] === ''
        ) {
            throw FailedToVerifyRelease::fromInvalidSubjectDefinition();
        }

        $pharFilename->verifyAgainstOther(new BinaryFile(
            $pharFilename->filePath,
            $decodedPayload['subject'][0]['digest']['sha256'],
        ));
    }

    /** @return non-empty-list<Attestation> */
    private function downloadAttestations(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename): array
    {
        $attestationUrl = $this->githubApiBaseUrl . '/orgs/php/attestations/sha256:' . $pharFilename->checksum;

        try {
            $decodedJson = $this->httpDownloader->get(
                $attestationUrl,
                [
                    'retry-auth-failure' => false,
                    'http' => [
                        'method' => 'GET',
                        'header' => $this->authHelper->addAuthenticationHeader([], $this->githubApiBaseUrl, $attestationUrl),
                    ],
                ],
            )->decodeJson();

            Assert::isArray($decodedJson);
            Assert::keyExists($decodedJson, 'attestations');
            Assert::isNonEmptyList($decodedJson['attestations']);

            return array_map(
                static function (array $attestation): Attestation {
                    return Attestation::fromAttestationBundleWithDsseEnvelope($attestation);
                },
                $decodedJson['attestations'],
            );
        } catch (TransportException $transportException) {
            if ($transportException->getStatusCode() === 404) {
                throw FailedToVerifyRelease::fromMissingAttestation($releaseMetadata, $pharFilename);
            }

            throw $transportException;
        }
    }
}
