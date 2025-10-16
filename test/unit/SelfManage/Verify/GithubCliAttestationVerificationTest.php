<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\Verify;

use Composer\IO\BufferIO;
use Composer\Util\Platform;
use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\SelfManage\Verify\FailedToVerifyRelease;
use Php\Pie\SelfManage\Verify\GithubCliAttestationVerification;
use Php\Pie\SelfManage\Verify\GithubCliNotAvailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;

#[CoversClass(GithubCliAttestationVerification::class)]
final class GithubCliAttestationVerificationTest extends TestCase
{
    private const FAKE_GH_CLI_HAPPY_SH    = __DIR__ . '/../../../assets/fake-gh-cli/happy.sh';
    private const FAKE_GH_CLI_UNHAPPY_SH  = __DIR__ . '/../../../assets/fake-gh-cli/unhappy.sh';
    private const FAKE_GH_CLI_HAPPY_BAT   = __DIR__ . '/../../../assets/fake-gh-cli/happy.bat';
    private const FAKE_GH_CLI_UNHAPPY_BAT = __DIR__ . '/../../../assets/fake-gh-cli/unhappy.bat';

    private ExecutableFinder&MockObject $executableFinder;
    private BufferIO $io;
    private GithubCliAttestationVerification $verifier;

    public function setUp(): void
    {
        parent::setUp();

        $this->executableFinder = $this->createMock(ExecutableFinder::class);
        $this->io               = new BufferIO();

        $this->verifier = new GithubCliAttestationVerification($this->executableFinder);
    }

    public function testPassingVerification(): void
    {
        $this->executableFinder
            ->method('find')
            ->willReturn(Platform::isWindows() ? self::FAKE_GH_CLI_HAPPY_BAT : self::FAKE_GH_CLI_HAPPY_SH);

        $this->verifier->verify(new ReleaseMetadata('1.2.3', 'https://path/to/download'), new BinaryFile('/path/to/phar', 'some-checksum'), $this->io);

        self::assertStringContainsString('Verified the new PIE version', $this->io->getOutput());
    }

    public function testCannotFindGhCli(): void
    {
        $this->executableFinder
            ->method('find')
            ->willReturn(null);

        $this->expectException(GithubCliNotAvailable::class);
        $this->verifier->verify(new ReleaseMetadata('1.2.3', 'https://path/to/download'), new BinaryFile('/path/to/phar', 'some-checksum'), $this->io);
    }

    public function testFailingVerification(): void
    {
        $this->executableFinder
            ->method('find')
            ->willReturn(Platform::isWindows() ? self::FAKE_GH_CLI_UNHAPPY_BAT : self::FAKE_GH_CLI_UNHAPPY_SH);

        $this->expectException(FailedToVerifyRelease::class);
        $this->verifier->verify(new ReleaseMetadata('1.2.3', 'https://path/to/download'), new BinaryFile('/path/to/phar', 'some-checksum'), $this->io);
    }
}
