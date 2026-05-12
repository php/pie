<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Util;

use Php\Pie\Util\Process;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process as SymfonyProcess;

#[CoversClass(Process::class)]
final class ProcessTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: bool}> */
    public static function permissionDeniedProvider(): array
    {
        return [
            'apt 1 denied stderr' => ['', 'Error: Could not open lock file /var/lib/dpkg/lock-frontend - open (13: Permission denied)', true],
            'apt 1 denied stdout' => ['Error: Could not open lock file /var/lib/dpkg/lock-frontend - open (13: Permission denied)', '', true],
            'apt 2 denied stderr' => ['', 'Error: Unable to acquire the dpkg frontend lock (/var/lib/dpkg/lock-frontend), are you root?', true],
            'apt 2 denied stdout' => ['Error: Unable to acquire the dpkg frontend lock (/var/lib/dpkg/lock-frontend), are you root?', '', true],
            'dnf denied stderr' => ['', 'Error: This command has to be run with superuser privileges (under the root user on most systems).', true],
            'dnf denied stdout' => ['Error: This command has to be run with superuser privileges (under the root user on most systems).', '', true],
            'apk denied stderr' => ['', 'ERROR: Unable to open log: Permission denied', true],
            'apk denied stdout' => ['ERROR: Unable to open log: Permission denied', '', true],
            'no permission denied' => ['some other error', 'exit code 1', false],
        ];
    }

    #[DataProvider('permissionDeniedProvider')]
    public function testProcessProbablyPermissionDenied(string $stdout, string $stderr, bool $expected): void
    {
        $symfonyProcess = $this->createMock(SymfonyProcess::class);
        $symfonyProcess->method('getOutput')->willReturn($stdout);
        $symfonyProcess->method('getErrorOutput')->willReturn($stderr);

        $exception = $this->createMock(ProcessFailedException::class);
        $exception->method('getProcess')->willReturn($symfonyProcess);

        self::assertSame($expected, Process::processProbablyPermissionDenied($exception));
    }
}
