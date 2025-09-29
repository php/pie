<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\Verify;

use Composer\Downloader\TransportException;
use Composer\Util\AuthHelper;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\SelfManage\Verify\FailedToVerifyRelease;
use Php\Pie\SelfManage\Verify\FallbackVerificationUsingOpenSsl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use ThePhpFoundation\Attestation\Verification\VerifyAttestationWithOpenSsl;

use function assert;
use function base64_encode;
use function extension_loaded;
use function file_put_contents;
use function is_string;
use function json_encode;
use function openssl_csr_new;
use function openssl_csr_sign;
use function openssl_pkey_new;
use function openssl_sign;
use function openssl_x509_export;
use function sprintf;
use function str_replace;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function trim;

use const OPENSSL_ALGO_SHA256;

#[CoversClass(FallbackVerificationUsingOpenSsl::class)]
final class FallbackVerificationUsingOpenSslTest extends TestCase
{
    private const TEST_GITHUB_URL   = 'http://test-github-url.localhost';
    private const DSSE_PAYLOAD_TYPE = 'application/vnd.in-toto+json';

    private ReleaseMetadata $release;
    private BinaryFile $downloadedPhar;
    private HttpDownloader&MockObject $httpDownloader;
    private AuthHelper&MockObject $authHelper;
    private BufferedOutput $output;
    private FallbackVerificationUsingOpenSsl $verifier;
    /** @var non-empty-string */
    private string $trustedRootFilePath;

    public function setUp(): void
    {
        parent::setUp();

        $this->release        = new ReleaseMetadata('1.2.3', self::TEST_GITHUB_URL . '/pie.phar');
        $this->downloadedPhar = new BinaryFile('/path/to/pie.phar', 'fake-checksum');

        $this->httpDownloader = $this->createMock(HttpDownloader::class);
        $this->authHelper     = $this->createMock(AuthHelper::class);
        $this->output         = new BufferedOutput();

        $trustedRootFilePath = tempnam(sys_get_temp_dir(), 'pie_test_trusted_root_file_path');
        assert(is_string($trustedRootFilePath));
        $this->trustedRootFilePath = $trustedRootFilePath;

        $this->verifier = new FallbackVerificationUsingOpenSsl(new VerifyAttestationWithOpenSsl($this->trustedRootFilePath, self::TEST_GITHUB_URL, $this->httpDownloader, $this->authHelper));
    }

    /** @return array{0: string, 1: string} */
    private function prepareCertificateAndSignature(string $dsseEnvelopePayload): array
    {
        $caPrivateKey = openssl_pkey_new();
        $caCsr        = openssl_csr_new(['CN' => 'pie-test-ca'], $caPrivateKey);
        $caCert       = openssl_csr_sign($caCsr, null, $caPrivateKey, 1);
        openssl_x509_export($caCert, $caPemCertificate);

        file_put_contents($this->trustedRootFilePath, json_encode([
            'mediaType' => 'application/vnd.dev.sigstore.trustedroot+json;version=0.1',
            'certificateAuthorities' => [
                [
                    'certChain' => [
                        'certificates' => [
                            [
                                'rawBytes' => trim(str_replace('-----BEGIN CERTIFICATE-----', '', str_replace('-----END CERTIFICATE-----', '', $caPemCertificate))),
                            ],
                        ],
                    ],
                ],
            ],
        ]));

        $tempOpensslConfig = tempnam(sys_get_temp_dir(), 'pie_openssl_test_config');
        file_put_contents($tempOpensslConfig, <<<'EOF'

[ req ]
default_bits = 2048
prompt = no
encrypt_key = no
default_md = sha1
distinguished_name = dn
x509_extensions = v3_req

[ dn ]

[ v3_req ]
1.3.6.1.4.1.57264.1.8 = ASN1:UTF8String:https://token.actions.githubusercontent.com
1.3.6.1.4.1.57264.1.12 = ASN1:UTF8String:https://github.com/php/pie
1.3.6.1.4.1.57264.1.16 = ASN1:UTF8String:https://github.com/php
EOF);
        $privateKey  = openssl_pkey_new();
        $csr         = openssl_csr_new(['commonName' => 'pie-test'], $privateKey, ['config' => $tempOpensslConfig]);
        $certificate = openssl_csr_sign($csr, $caCert, $caPrivateKey, 1, [
            'config' => $tempOpensslConfig,
            'x509_extensions' => 'v3_req',
        ]);
        openssl_x509_export($certificate, $pemCertificate);

        openssl_sign(
            sprintf(
                'DSSEv1 %d %s %d %s',
                strlen(self::DSSE_PAYLOAD_TYPE),
                self::DSSE_PAYLOAD_TYPE,
                strlen($dsseEnvelopePayload),
                $dsseEnvelopePayload,
            ),
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256,
        );

        return [$pemCertificate, $signature];
    }

    private function mockAttestationResponse(string $digestInUrl, string $dsseEnvelopePayload, string $signature, string $pemCertificate): void
    {
        $url = self::TEST_GITHUB_URL . '/orgs/php/attestations/sha256:' . $digestInUrl;
        $this->authHelper
            ->method('addAuthenticationHeader')
            ->willReturn(['Authorization: Bearer fake-token']);
        $this->httpDownloader->expects(self::once())
            ->method('get')
            ->with(
                $url,
                [
                    'retry-auth-failure' => true,
                    'http' => [
                        'method' => 'GET',
                        'header' => ['Authorization: Bearer fake-token'],
                    ],
                ],
            )
            ->willReturn(
                new Response(
                    ['url' => $url],
                    200,
                    [],
                    json_encode([
                        'attestations' => [
                            [
                                'bundle' => [
                                    'verificationMaterial' => [
                                        'certificate' => ['rawBytes' => trim(str_replace('-----BEGIN CERTIFICATE-----', '', str_replace('-----END CERTIFICATE-----', '', $pemCertificate)))],
                                    ],
                                    'dsseEnvelope' => [
                                        'payload' => base64_encode($dsseEnvelopePayload),
                                        'payloadType' => self::DSSE_PAYLOAD_TYPE,
                                        'signatures' => [['sig' => base64_encode($signature)]],
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ),
            );
    }

    public function testSuccessfulVerify(): void
    {
        if (! extension_loaded('openssl')) {
            self::markTestSkipped('Cannot run tests without openssl extension');
        }

        $dsseEnvelopePayload = json_encode([
            'subject' => [
                [
                    'name' => 'pie.phar',
                    'digest' => ['sha256' => $this->downloadedPhar->checksum],
                ],
            ],
        ]);

        [$pemCertificate, $signature] = $this->prepareCertificateAndSignature($dsseEnvelopePayload);

        $this->mockAttestationResponse($this->downloadedPhar->checksum, $dsseEnvelopePayload, $signature, $pemCertificate);

        $this->verifier->verify($this->release, $this->downloadedPhar, $this->output);

        self::assertStringContainsString('Verified the new PIE version (using fallback verification)', $this->output->fetch());
    }

    public function testFailedToVerifyBecauseDigestMismatch(): void
    {
        if (! extension_loaded('openssl')) {
            self::markTestSkipped('Cannot run tests without openssl extension');
        }

        $dsseEnvelopePayload = json_encode([
            'subject' => [
                [
                    'name' => 'pie.phar',
                    'digest' => ['sha256' => 'different-checksum'],
                ],
            ],
        ]);

        [$pemCertificate, $signature] = $this->prepareCertificateAndSignature($dsseEnvelopePayload);

        $this->mockAttestationResponse($this->downloadedPhar->checksum, $dsseEnvelopePayload, $signature, $pemCertificate);

        $this->expectException(FailedToVerifyRelease::class);
        $this->verifier->verify($this->release, $this->downloadedPhar, $this->output);
    }

    public function testFailedToVerifyBecauseSignatureVerificationFailed(): void
    {
        if (! extension_loaded('openssl')) {
            self::markTestSkipped('Cannot run tests without openssl extension');
        }

        $dsseEnvelopePayload = json_encode([
            'subject' => [
                [
                    'name' => 'pie.phar',
                    'digest' => ['sha256' => $this->downloadedPhar->checksum],
                ],
            ],
        ]);

        [$pemCertificate, $signature] = $this->prepareCertificateAndSignature($dsseEnvelopePayload);

        $this->mockAttestationResponse(
            $this->downloadedPhar->checksum,
            json_encode([
                'subject' => [
                    [
                        'name' => 'pie.phar',
                        'digest' => ['sha256' => $this->downloadedPhar->checksum],
                        'i-tampered-with-this-payload-hahahaha' => true,
                    ],
                ],
            ]),
            $signature,
            $pemCertificate,
        );

        $this->expectException(FailedToVerifyRelease::class);
        $this->verifier->verify($this->release, $this->downloadedPhar, $this->output);
    }

    public function testFailedToVerifyBecauseDigestNotFoundOnGitHub(): void
    {
        if (! extension_loaded('openssl')) {
            self::markTestSkipped('Cannot run tests without openssl extension');
        }

        $transportException = new TransportException('404 Not Found');
        $transportException->setStatusCode(404);

        $this->authHelper
            ->method('addAuthenticationHeader')
            ->willReturn(['Authorization: Bearer fake-token']);
        $this->httpDownloader->expects(self::once())
            ->method('get')
            ->willThrowException($transportException);

        $this->expectException(FailedToVerifyRelease::class);
        $this->verifier->verify($this->release, $this->downloadedPhar, $this->output);
    }
}
