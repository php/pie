<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\Verify;

use Php\Pie\File\BinaryFile;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\SelfManage\Verify\FailedToVerifyRelease;
use Php\Pie\SelfManage\Verify\GithubCliAttestationVerification;
use Php\Pie\SelfManage\Verify\GithubCliNotAvailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\ExecutableFinder;

#[CoversClass(GithubCliAttestationVerification::class)]
final class GithubCliAttestationVerificationTest extends TestCase
{
    private const FAKE_GH_CLI_HAPPY   = __DIR__ . '/../../../assets/fake-gh-cli/happy.sh';
    private const FAKE_GH_CLI_UNHAPPY = __DIR__ . '/../../../assets/fake-gh-cli/unhappy.sh';

    private ExecutableFinder&MockObject $executableFinder;
    private BufferedOutput $output;
    private GithubCliAttestationVerification $verifier;

    public function setUp(): void
    {
        parent::setUp();

        $this->executableFinder = $this->createMock(ExecutableFinder::class);
        $this->output           = new BufferedOutput();

        $this->verifier = new GithubCliAttestationVerification($this->executableFinder);
    }

    public function testPassingVerification(): void
    {
        $this->executableFinder
            ->method('find')
            ->willReturn(self::FAKE_GH_CLI_HAPPY);

        $this->verifier->verify(new ReleaseMetadata('1.2.3', 'https://path/to/download'), new BinaryFile('/path/to/phar', 'some-checksum'), $this->output);

        self::assertStringContainsString('Verified the new PIE version', $this->output->fetch());
    }

    public function testCannotFindGhCli(): void
    {
        $this->executableFinder
            ->method('find')
            ->willReturn(null);

        $this->expectException(GithubCliNotAvailable::class);
        $this->verifier->verify(new ReleaseMetadata('1.2.3', 'https://path/to/download'), new BinaryFile('/path/to/phar', 'some-checksum'), $this->output);
    }

    public function testFailingVerification(): void
    {
        $this->executableFinder
            ->method('find')
            ->willReturn(self::FAKE_GH_CLI_UNHAPPY);

        $this->expectException(FailedToVerifyRelease::class);
        $this->verifier->verify(new ReleaseMetadata('1.2.3', 'https://path/to/download'), new BinaryFile('/path/to/phar', 'some-checksum'), $this->output);
    }
}
