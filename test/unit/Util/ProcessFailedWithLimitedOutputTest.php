<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Util;

use Composer\Util\Platform;
use Php\Pie\Util\ProcessFailedWithLimitedOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use function clearstatcache;
use function file_exists;
use function Safe\fileperms;
use function Safe\preg_match;
use function Safe\unlink;
use function sprintf;
use function umask;

use const PHP_BINARY;

#[CoversClass(ProcessFailedWithLimitedOutput::class)]
final class ProcessFailedWithLimitedOutputTest extends TestCase
{
    private int $originalUmask;
    private string|null $outputFile = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->originalUmask = umask(0022);
    }

    public function tearDown(): void
    {
        umask($this->originalUmask);

        if ($this->outputFile !== null && file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }

        parent::tearDown();
    }

    public function testOutputFileIsOnlyReadableByOwner(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('This test can only run on non-Windows');
        }

        $process = new Process([PHP_BINARY, '-r', 'echo str_repeat("a", 6000); exit(1);']);
        $process->run();

        $exception = ProcessFailedWithLimitedOutput::fromProcessFailedException(new ProcessFailedException($process));

        self::assertInstanceOf(ProcessFailedWithLimitedOutput::class, $exception);
        preg_match('/Output was saved in "([^"]+)"/', $exception->getMessage(), $matches);
        self::assertArrayHasKey(1, $matches);
        $this->outputFile = $matches[1];

        clearstatcache();
        self::assertSame(
            '0600',
            sprintf('%04o', fileperms($this->outputFile) & 0777),
        );
    }
}
