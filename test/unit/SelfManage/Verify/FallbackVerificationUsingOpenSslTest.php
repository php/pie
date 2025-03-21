<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\Verify;

use Composer\Downloader\TransportException;
use Composer\Util\AuthHelper;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Php\Pie\File\BinaryFile;
use Php\Pie\File\BinaryFileFailedVerification;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\SelfManage\Verify\FailedToVerifyRelease;
use Php\Pie\SelfManage\Verify\FallbackVerificationUsingOpenSsl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function base64_encode;
use function extension_loaded;
use function json_encode;
use function openssl_csr_new;
use function openssl_csr_sign;
use function openssl_pkey_new;
use function openssl_sign;
use function openssl_x509_export;
use function sprintf;
use function str_replace;
use function strlen;
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

    public function setUp(): void
    {
        parent::setUp();

        $this->release        = new ReleaseMetadata('1.2.3', self::TEST_GITHUB_URL . '/pie.phar');
        $this->downloadedPhar = new BinaryFile('/path/to/pie.phar', 'fake-checksum');

        $this->httpDownloader = $this->createMock(HttpDownloader::class);
        $this->authHelper     = $this->createMock(AuthHelper::class);
        $this->output         = new BufferedOutput();

        $this->verifier = new FallbackVerificationUsingOpenSsl(self::TEST_GITHUB_URL, $this->httpDownloader, $this->authHelper);
    }

    /** @return array{0: string, 1: string} */
    private function prepareCertificateAndSignature(string $dsseEnvelopePayload): array
    {
        $privateKey  = openssl_pkey_new();
        $csr         = openssl_csr_new(['commonName' => 'pie-test'], $privateKey);
        $certificate = openssl_csr_sign($csr, null, $privateKey, 1);
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
                    'retry-auth-failure' => false,
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

        $this->expectException(BinaryFileFailedVerification::class);
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
