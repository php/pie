<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\Verify;

use Composer\IO\BufferIO;
use Php\Pie\SelfManage\Update\ReleaseMetadata;
use Php\Pie\SelfManage\Verify\RecoverFromFailedVerification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(RecoverFromFailedVerification::class)]
final class RecoverFromFailedVerificationTest extends TestCase
{
    private const DOWNLOAD_URL = 'https://example.localhost/pie.phar';

    private ReleaseMetadata $release;
    private RuntimeException $verificationFailure;
    private RecoverFromFailedVerification $recover;

    public function setUp(): void
    {
        parent::setUp();

        $this->release             = new ReleaseMetadata('1.2.3', self::DOWNLOAD_URL);
        $this->verificationFailure = new RuntimeException('some failure');
        $this->recover             = new RecoverFromFailedVerification();
    }

    public function testAbortsWithoutPromptingWhenNonInteractive(): void
    {
        $io = new BufferIO();

        $result = ($this->recover)($io, $this->release, $this->verificationFailure);

        self::assertFalse($result);
        $output = $io->getOutput();
        self::assertStringContainsString(self::DOWNLOAD_URL, $output);
        self::assertStringContainsString('not running in interactive mode', $output);
    }

    public function testAbortsWhenUserDeclinesConfirmation(): void
    {
        $io = new BufferIO();
        $io->setUserInputs(['n']);

        $result = ($this->recover)($io, $this->release, $this->verificationFailure);

        self::assertFalse($result);
        $output = $io->getOutput();
        self::assertStringContainsString(self::DOWNLOAD_URL, $output);
        self::assertStringContainsString('aborting', $output);
    }

    public function testContinuesWhenUserConfirms(): void
    {
        $io = new BufferIO();
        $io->setUserInputs(['y']);

        $result = ($this->recover)($io, $this->release, $this->verificationFailure);

        self::assertTrue($result);
        $output = $io->getOutput();
        self::assertStringContainsString(self::DOWNLOAD_URL, $output);
        self::assertStringContainsString('at your own risk', $output);
    }
}
