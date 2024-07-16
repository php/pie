<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform\TargetPhp;

use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

use function array_combine;
use function array_map;
use function assert;
use function defined;
use function file_exists;
use function get_loaded_extensions;
use function ini_get;
use function is_executable;
use function php_uname;
use function phpversion;
use function sprintf;
use function trim;

use const PHP_INT_SIZE;
use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;
use const PHP_RELEASE_VERSION;

#[CoversClass(PhpBinaryPath::class)]
final class PhpBinaryPathTest extends TestCase
{
    public function testVersionFromCurrentProcess(): void
    {
        $phpBinary = PhpBinaryPath::fromCurrentProcess();

        self::assertSame(
            sprintf('%s.%s.%s', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION),
            $phpBinary->version(),
        );
        self::assertNull($phpBinary->phpConfigPath());
    }

    public function testFromPhpConfigExecutable(): void
    {
        $process             = (new Process(['which', 'php-config']));
        $exitCode            = $process->run();
        $phpConfigExecutable = trim($process->getOutput());

        if ($exitCode !== 0 || ! file_exists($phpConfigExecutable) || ! is_executable($phpConfigExecutable) || $phpConfigExecutable === '') {
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

        self::assertSame($phpConfigExecutable, $phpBinary->phpConfigPath());
    }

    public function testExtensions(): void
    {
        $exts        = get_loaded_extensions();
        $extVersions = array_map(
            static function ($extension) {
                $extVersion = phpversion($extension);
                if ($extVersion === false) {
                    return '0';
                }

                return $extVersion;
            },
            $exts,
        );
        self::assertSame(
            array_combine($exts, $extVersions),
            PhpBinaryPath::fromCurrentProcess()
                ->extensions(),
        );
    }

    public function testOperatingSystem(): void
    {
        self::assertSame(
            defined('PHP_WINDOWS_VERSION_BUILD') ? OperatingSystem::Windows : OperatingSystem::NonWindows,
            PhpBinaryPath::fromCurrentProcess()
                ->operatingSystem(),
        );
    }

    public function testMajorMinorVersion(): void
    {
        self::assertSame(
            PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            PhpBinaryPath::fromCurrentProcess()
                ->majorMinorVersion(),
        );
    }

    public function testMachineType(): void
    {
        $myUnameMachineType = php_uname('m');
        assert($myUnameMachineType !== '');
        self::assertSame(
            Architecture::parseArchitecture($myUnameMachineType),
            PhpBinaryPath::fromCurrentProcess()
                ->machineType(),
        );
    }

    public function testPhpIntSize(): void
    {
        self::assertSame(
            PHP_INT_SIZE,
            PhpBinaryPath::fromCurrentProcess()
                ->phpIntSize(),
        );
    }

    public function testExtensionPath(): void
    {
        self::assertSame(
            ini_get('extension_dir'),
            PhpBinaryPath::fromCurrentProcess()
                ->extensionPath(),
        );
    }
}
