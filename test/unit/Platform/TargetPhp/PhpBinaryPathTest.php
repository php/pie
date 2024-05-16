<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform\TargetPhp;

use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

use function file_exists;
use function is_executable;
use function sprintf;
use function trim;

use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;
use const PHP_RELEASE_VERSION;

#[CoversClass(PhpBinaryPath::class)]
final class PhpBinaryPathTest extends TestCase
{
    public function testVersionFromCurrentProcess(): void
    {
        self::assertSame(
            sprintf('%s.%s.%s', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION),
            PhpBinaryPath::fromCurrentProcess()->version(),
        );
    }

    public function testFromPhpConfigExecutable(): void
    {
        $process             = (new Process(['which', 'php-config']));
        $exitCode            = $process->run();
        $phpConfigExecutable = trim($process->getOutput());

        if ($exitCode !== 0 || ! file_exists($phpConfigExecutable) || ! is_executable($phpConfigExecutable)) {
            self::markTestSkipped('Needs php-config in path to run this test');
        }

        $phpBinary = PhpBinaryPath::fromPhpConfigExecutable($phpConfigExecutable);

        // NOTE: this makes an assumption that the `php-config` in path is the same as the version being executed
        // In most cases, this will be the cases (e.g. in CI, running locally), but if you're trying to test this and
        // the versions are not matching, that's probably why.
        // @todo improve this assertion in future, if it becomes problematic
        self::assertSame(
            sprintf('%s.%s.%s', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION),
            $phpBinary->version(),
        );
    }

    public function testPhpIntSize(): void
    {
        self::assertSame(
            PHP_INT_SIZE,
            PhpBinaryPath
                ::fromCurrentProcess()
                ->phpIntSize()
        );
    }
}
