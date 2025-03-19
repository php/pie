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
use function base64_decode;
use function count;
use function extension_loaded;
use function is_array;
use function is_string;
use function json_decode;
use function openssl_pkey_get_public;
use function openssl_verify;
use function sprintf;
use function strlen;
use function wordwrap;

use const OPENSSL_ALGO_SHA256;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class VerifyPieReleaseUsingAttestation implements VerifyPiePhar
{
    public function __construct(
        private readonly string $githubApiBaseUrl,
        private readonly HttpDownloader $httpDownloader,
        private readonly AuthHelper $authHelper,
    ) {
    }

    public function verify(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename, OutputInterface $output): void
    {
        // @todo prefer this $this->verifyUsingGhCli();

        $this->verifyUsingOpenSSL($releaseMetadata, $pharFilename, $output);
    }

    private function verifyUsingGhCli(): void
    {
        // @todo verify using `gh attestation verify` etc
    }

    private function verifyUsingOpenSSL(ReleaseMetadata $releaseMetadata, BinaryFile $pharFilename, OutputInterface $output): void
    {
        if (! extension_loaded('openssl')) {
            throw FailedToVerifyRelease::fromNoOpenssl();
        }

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
            $this->assertDigestFromAttestationMatchesActual($pharFilename, $attestation['dsseEnvelopePayload']);

            $publicKey = openssl_pkey_get_public($attestation['certificate']);
            Assert::isInstanceOf($publicKey, OpenSSLAsymmetricKey::class);

            $preAuthenticationEncoding = sprintf(
                'DSSEv1 %d %s %d %s',
                strlen($attestation['dsseEnvelopePayloadType']),
                $attestation['dsseEnvelopePayloadType'],
                strlen($attestation['dsseEnvelopePayload']),
                $attestation['dsseEnvelopePayload'],
            );

            if (openssl_verify($preAuthenticationEncoding, $attestation['dsseEnvelopeSignature'], $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
                throw FailedToVerifyRelease::fromSignatureVerificationFailed($attestationIndex, $releaseMetadata);
            }
        }

        $output->writeln('<info>✅ Verified the new PIE (using fallback verification)</info>');
    }

    private function assertDigestFromAttestationMatchesActual(BinaryFile $pharFilename, string $dsseEnvelopePayload): void
    {
        /** @var mixed $decodedPayload */
        $decodedPayload = json_decode($dsseEnvelopePayload, true);

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

    /**
     * @return non-empty-list<array{
     *     certificate: non-empty-string,
     *     dsseEnvelopePayload: non-empty-string,
     *     dsseEnvelopePayloadType: non-empty-string,
     *     dsseEnvelopeSignature: non-empty-string,
     * }>
     */
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
                static function (array $attestation): array {
                    Assert::keyExists($attestation, 'bundle');
                    Assert::isArray($attestation['bundle']);

                    Assert::keyExists($attestation['bundle'], 'verificationMaterial');
                    Assert::isArray($attestation['bundle']['verificationMaterial']);
                    Assert::keyExists($attestation['bundle']['verificationMaterial'], 'certificate');
                    Assert::isArray($attestation['bundle']['verificationMaterial']['certificate']);
                    Assert::keyExists($attestation['bundle']['verificationMaterial']['certificate'], 'rawBytes');
                    Assert::stringNotEmpty($attestation['bundle']['verificationMaterial']['certificate']['rawBytes']);

                    Assert::keyExists($attestation['bundle'], 'dsseEnvelope');
                    Assert::isArray($attestation['bundle']['dsseEnvelope']);
                    Assert::keyExists($attestation['bundle']['dsseEnvelope'], 'payload');
                    Assert::stringNotEmpty($attestation['bundle']['dsseEnvelope']['payload']);
                    Assert::keyExists($attestation['bundle']['dsseEnvelope'], 'payloadType');
                    Assert::stringNotEmpty($attestation['bundle']['dsseEnvelope']['payloadType']);
                    Assert::keyExists($attestation['bundle']['dsseEnvelope'], 'signatures');
                    Assert::isNonEmptyList($attestation['bundle']['dsseEnvelope']['signatures']);
                    Assert::count($attestation['bundle']['dsseEnvelope']['signatures'], 1);
                    Assert::keyExists($attestation['bundle']['dsseEnvelope']['signatures'], 0);
                    Assert::isArray($attestation['bundle']['dsseEnvelope']['signatures'][0]);
                    Assert::keyExists($attestation['bundle']['dsseEnvelope']['signatures'][0], 'sig');
                    Assert::stringNotEmpty($attestation['bundle']['dsseEnvelope']['signatures'][0]['sig']);

                    $decoratedCertificate = "-----BEGIN CERTIFICATE-----\n"
                        . wordwrap($attestation['bundle']['verificationMaterial']['certificate']['rawBytes'], 67, "\n", true) . "\n"
                        . "-----END CERTIFICATE-----\n";
                    Assert::stringNotEmpty($decoratedCertificate);

                    $decodedPayload = base64_decode($attestation['bundle']['dsseEnvelope']['payload']);
                    Assert::stringNotEmpty($decodedPayload);

                    $decodedSignature = base64_decode($attestation['bundle']['dsseEnvelope']['signatures'][0]['sig']);
                    Assert::stringNotEmpty($decodedSignature);

                    return [
                        'certificate' => $decoratedCertificate,
                        'dsseEnvelopePayload' => $decodedPayload,
                        'dsseEnvelopePayloadType' => $attestation['bundle']['dsseEnvelope']['payloadType'],
                        'dsseEnvelopeSignature' => $decodedSignature,
                    ];
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
