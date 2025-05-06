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
use function explode;
use function extension_loaded;
use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;
use function openssl_pkey_get_public;
use function openssl_verify;
use function openssl_x509_parse;
use function openssl_x509_verify;
use function ord;
use function sprintf;
use function strlen;
use function substr;
use function trim;
use function wordwrap;

use const OPENSSL_ALGO_SHA256;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class FallbackVerificationUsingOpenSsl implements VerifyPiePhar
{
    public const TRUSTED_ROOT_FILE_PATH = __DIR__ . '/../../../resources/trusted-root.jsonl';

    /** @link https://github.com/sigstore/fulcio/blob/main/docs/oid-info.md#136141572641--fulcio */
    private const ATTESTATION_CERTIFICATE_EXPECTED_EXTENSION_VALUES = [
        '1.3.6.1.4.1.57264.1.8' => 'https://token.actions.githubusercontent.com',
        '1.3.6.1.4.1.57264.1.12' => 'https://github.com/php/pie',
        '1.3.6.1.4.1.57264.1.16' => 'https://github.com/php',
    ];

    public function __construct(
        private readonly string $trustedRootFilePath,
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
            $this->assertCertificateSignedByTrustedRoot($attestation);
            $output->writeln('#' . $attestationIndex . ': Certificate was signed by a trusted root.', OutputInterface::VERBOSITY_VERBOSE);

            $this->assertCertificateExtensionClaims($attestation);
            $output->writeln('#' . $attestationIndex . ': Certificate extension claims match.', OutputInterface::VERBOSITY_VERBOSE);

            $this->assertDigestFromAttestationMatchesActual($pharFilename, $attestation);
            $output->writeln('#' . $attestationIndex . ': Payload digest matches downloaded file.', OutputInterface::VERBOSITY_VERBOSE);

            $this->verifyDsseEnvelopeSignature($releaseMetadata, $attestationIndex, $attestation);
            $output->writeln('#' . $attestationIndex . ': DSSE payload signature verified with certificate.', OutputInterface::VERBOSITY_VERBOSE);
        }

        $output->writeln('<info>✅ Verified the new PIE version (using fallback verification)</info>');
    }

    private function assertCertificateSignedByTrustedRoot(Attestation $attestation): void
    {
        $attestationCertificateInfo = openssl_x509_parse($attestation->certificate);

        // @todo process in place to make sure this gets updated frequently enough: gh attestation trusted-root > resources/trusted-root.jsonl
        $trustedRootJsonLines = explode("\n", trim(file_get_contents($this->trustedRootFilePath)));

        /**
         * Now go through our trusted root certificates and attempt to verify that the certificate was signed by an
         * in-date trusted root certificate. The root certificates should be periodically and frequently updated using:
         *
         *     gh attestation trusted-root > resources/trusted-root.jsonl
         *
         * And verifying the contents afterwards to ensure they have not been compromised. This list of JSON blobs may
         * have multiple certificates (e.g. root certificates, intermediate certificates, expired certificates, etc.)
         * so we should loop over to find the correct certificate used to sign the attestation certificate.
         */
        foreach ($trustedRootJsonLines as $jsonLine) {
            /** @var mixed $decoded */
            $decoded = json_decode($jsonLine, true);

            // No certificate authorities defined in this JSON line, skip it...
            if (
                ! is_array($decoded)
                || ! array_key_exists('certificateAuthorities', $decoded)
                || ! is_array($decoded['certificateAuthorities'])
            ) {
                continue;
            }

            /** @var mixed $certificateAuthority */
            foreach ($decoded['certificateAuthorities'] as $certificateAuthority) {
                // We don't have a certificate chain defined, skip it...
                if (
                    ! is_array($certificateAuthority)
                    || ! array_key_exists('certChain', $certificateAuthority)
                    || ! is_array($certificateAuthority['certChain'])
                    || ! array_key_exists('certificates', $certificateAuthority['certChain'])
                    || ! is_array($certificateAuthority['certChain']['certificates'])
                ) {
                    continue;
                }

                /** @var mixed $caCertificateWrapper */
                foreach ($certificateAuthority['certChain']['certificates'] as $caCertificateWrapper) {
                    // Certificate is not in the expected format, i.e. no rawBytes key, skip it...
                    if (
                        ! is_array($caCertificateWrapper)
                        || ! array_key_exists('rawBytes', $caCertificateWrapper)
                        || ! is_string($caCertificateWrapper['rawBytes'])
                        || $caCertificateWrapper['rawBytes'] === ''
                    ) {
                        continue;
                    }

                    // Embed the base64-encoded DER into a PEM envelope for consumption by OpenSSL.
                    $caCertificateString = sprintf(
                        <<<'EOT'
                        -----BEGIN CERTIFICATE-----
                        %s
                        -----END CERTIFICATE-----
                        EOT,
                        wordwrap($caCertificateWrapper['rawBytes'], 67, "\n", true),
                    );

                    $caCertificateInfo = openssl_x509_parse($caCertificateString);

                    // If the CA certificate subject is not the issuer of the attestation certificate,
                    // this was not the cert we were looking for, skip it...
                    if ($caCertificateInfo['subject'] !== $attestationCertificateInfo['issuer']) {
                        continue;
                    }

                    // Finally, verify that the located CA cert was used to sign the attestation certificate
                    if (openssl_x509_verify($attestation->certificate, $caCertificateString) !== 1) {
                        /** @psalm-suppress MixedArgument */
                        throw FailedToVerifyRelease::fromIssuerCertificateVerificationFailed($attestationCertificateInfo['issuer']);
                    }

                    return;
                }
            }
        }

        /**
         * If we got here, we skipped all the certificates in the trusted root collection for various reasons; so we
         * therefore cannot trust the attestation certificate.
         *
         * @psalm-suppress MixedArgument
         */
        throw FailedToVerifyRelease::fromNoIssuerCertificateInTrustedRoot($attestationCertificateInfo['issuer']);
    }

    private function assertCertificateExtensionClaims(Attestation $attestation): void
    {
        $attestationCertificateInfo = openssl_x509_parse($attestation->certificate);
        Assert::isArray($attestationCertificateInfo['extensions']);

        /**
         * See {@link https://github.com/sigstore/fulcio/blob/main/docs/oid-info.md#136141572641--fulcio} for details
         * on the Fulcio extension keys; note the values are DER-encoded strings; the ASN.1 tag is UTF8String (0x0C).
         *
         * Check the extension values are what we expect; these are hard-coded, as we don't expect them
         * to change unless the namespace/repo name change, etc.
         */
        foreach (self::ATTESTATION_CERTIFICATE_EXPECTED_EXTENSION_VALUES as $extension => $expectedValue) {
            Assert::keyExists($attestationCertificateInfo['extensions'], $extension);
            Assert::stringNotEmpty($attestationCertificateInfo['extensions'][$extension]);
            $actualValue = $attestationCertificateInfo['extensions'][$extension];

            // First character (the ASN.1 tag) is expected to be UTF8String (0x0C)
            if (ord($actualValue[0]) !== 0x0C) {
                throw FailedToVerifyRelease::fromMismatchingExtensionValues($extension, $expectedValue, $actualValue);
            }

            /**
             * Second character is expected to be the length of the actual value
             * as long as they are less than 127 bytes (short form)
             *
             * @link https://www.oss.com/asn1/resources/asn1-made-simple/asn1-quick-reference/basic-encoding-rules.html#Lengths
             */
            $expectedValueLength = ord($actualValue[1]);
            if (strlen($actualValue) !== 2 + $expectedValueLength) {
                throw FailedToVerifyRelease::fromInvalidDerEncodedStringLength($actualValue, 2 + $expectedValueLength);
            }

            $derDecodedValue = substr($actualValue, 2, $expectedValueLength);
            if ($derDecodedValue !== $expectedValue) {
                throw FailedToVerifyRelease::fromMismatchingExtensionValues($extension, $expectedValue, $derDecodedValue);
            }
        }
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
                    'retry-auth-failure' => true,
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
