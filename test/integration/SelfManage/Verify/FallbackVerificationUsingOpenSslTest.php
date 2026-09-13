<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\SelfManage\Verify;

use Composer\Downloader\TransportException;
use Composer\IO\BufferIO;
use LogicException;
use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\FetchPieRelease;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\SelfManage\Verify\FailedToVerifyRelease;
use Php\Pie\SelfManage\Verify\FallbackVerificationUsingOpenSsl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ThePhpFoundation\Attestation\BundleSource\BundleSource;
use ThePhpFoundation\Attestation\BundleSource\OnDiskBundle;

use function str_repeat;

#[CoversClass(FallbackVerificationUsingOpenSsl::class)]
final class FallbackVerificationUsingOpenSslTest extends TestCase
{
    private const RELEASE_TAG            = '1.4.10';
    private const RELEASE_PHAR_CHECKSUM  = 'b88792235c8e80be568436d4cb043b49fd1869c89b64e83d23e2882ae19d70a8';
    private const RELEASE_BUNDLE_FIXTURE = __DIR__ . '/../../../assets/pie-1.4.10-bundle.json';

    private const NIGHTLY_TRUNK_BRANCH   = '1.5.x';
    private const NIGHTLY_PHAR_CHECKSUM  = 'a46a29d77d8606bd84857b2b13e5581794bc3a43b2af44de64ba2b4c65511c49';
    private const NIGHTLY_BUNDLE_FIXTURE = __DIR__ . '/../../../assets/pie-nightly-bundle.json';

    private ReleaseMetadata $release;
    private BinaryFile $downloadedPhar;
    private BufferIO $io;
    private FetchPieRelease&MockObject $fetchPieRelease;

    public function setUp(): void
    {
        parent::setUp();

        $this->release        = new ReleaseMetadata(self::RELEASE_TAG, 'http://test-github-url.localhost/pie.phar');
        $this->downloadedPhar = new BinaryFile('/path/to/pie.phar', self::RELEASE_PHAR_CHECKSUM);

        $this->io              = new BufferIO();
        $this->fetchPieRelease = $this->createMock(FetchPieRelease::class);
    }

    public function testSuccessfulVerify(): void
    {
        $verifier = new FallbackVerificationUsingOpenSsl($this->fetchPieRelease, new OnDiskBundle(self::RELEASE_BUNDLE_FIXTURE));

        $verifier->verify($this->release, $this->downloadedPhar, $this->io);

        self::assertStringContainsString('Verified the new PIE version (using fallback verification)', $this->io->getOutput());
    }

    public function testSuccessfulVerifyForNightly(): void
    {
        $nightlyRelease = new ReleaseMetadata('nightly', 'http://test-github-url.localhost/pie-nightly.phar');
        $nightlyPhar    = new BinaryFile('/path/to/pie-nightly.phar', self::NIGHTLY_PHAR_CHECKSUM);

        $this->fetchPieRelease->method('trunkBranch')->willReturn(self::NIGHTLY_TRUNK_BRANCH);

        $verifier = new FallbackVerificationUsingOpenSsl($this->fetchPieRelease, new OnDiskBundle(self::NIGHTLY_BUNDLE_FIXTURE));

        $verifier->verify($nightlyRelease, $nightlyPhar, $this->io);

        self::assertStringContainsString('Verified the new PIE version (using fallback verification)', $this->io->getOutput());
    }

    public function testFailedToVerifyBecauseDigestMismatch(): void
    {
        $wrongChecksumPhar = new BinaryFile('/path/to/pie.phar', str_repeat('f', 64));

        $verifier = new FallbackVerificationUsingOpenSsl($this->fetchPieRelease, new OnDiskBundle(self::RELEASE_BUNDLE_FIXTURE));

        $this->expectException(FailedToVerifyRelease::class);
        $verifier->verify($this->release, $wrongChecksumPhar, $this->io);
    }

    public function testFailedToVerifyBecauseGithubAuthenticationFailed(): void
    {
        $transportException = new TransportException('401 Unauthorized');
        $transportException->setStatusCode(401);

        $bundleSource = $this->createMock(BundleSource::class);
        $bundleSource->method('getBundles')->willThrowException($transportException);

        $verifier = new FallbackVerificationUsingOpenSsl($this->fetchPieRelease, $bundleSource);

        $this->expectException(FailedToVerifyRelease::class);
        $verifier->verify($this->release, $this->downloadedPhar, $this->io);
    }

    public function testUnexpectedThrowableIsWrappedInFailedToVerifyRelease(): void
    {
        $bundleSource = $this->createMock(BundleSource::class);
        $bundleSource->method('getBundles')->willThrowException(new LogicException('Something unexpected happened'));

        $verifier = new FallbackVerificationUsingOpenSsl($this->fetchPieRelease, $bundleSource);

        $this->expectException(FailedToVerifyRelease::class);
        $this->expectExceptionMessageMatches('/Something unexpected happened/');
        $verifier->verify($this->release, $this->downloadedPhar, $this->io);
    }
}
