<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform\TargetPhp;

use Composer\Util\Platform;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\Exception\InvalidPhpBinaryPath;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpExecutableFinder;

use function array_column;
use function array_combine;
use function array_filter;
use function array_map;
use function array_unique;
use function assert;
use function defined;
use function dirname;
use function file_exists;
use function get_loaded_extensions;
use function ini_get;
use function is_dir;
use function is_executable;
use function php_uname;
use function phpversion;
use function sprintf;

use const DIRECTORY_SEPARATOR;
use const PHP_INT_SIZE;
use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;
use const PHP_OS_FAMILY;
use const PHP_RELEASE_VERSION;

#[CoversClass(PhpBinaryPath::class)]
final class PhpBinaryPathTest extends TestCase
{
    private const FAKE_PHP_EXECUTABLE = __DIR__ . '/../../../assets/fake-php.sh';

    public function testNonExistentPhpBinaryIsRejected(): void
    {
        $this->expectException(InvalidPhpBinaryPath::class);
        $this->expectExceptionMessage('does not exist');
        PhpBinaryPath::fromPhpBinaryPath(__DIR__ . '/path/to/a/non/existent/php/binary');
    }

    public function testNonExecutablePhpBinaryIsRejected(): void
    {
        if (Platform::isWindows()) {
            /**
             * According to the {@link https://www.php.net/manual/en/function.is-executable.php}:
             *
             *     for BC reasons, files with a .bat or .cmd extension are also considered executable
             *
             * However, that does not seem to be the case; calling {@see is_executable} always seems to return false,
             * even with a `.bat` file.
             */
            self::markTestSkipped('is_executable always returns false on Windows it seems...');
        }

        $this->expectException(InvalidPhpBinaryPath::class);
        $this->expectExceptionMessage('is not executable');
        PhpBinaryPath::fromPhpBinaryPath(__FILE__);
    }

    public function testInvalidPhpBinaryIsRejected(): void
    {
        $this->expectException(InvalidPhpBinaryPath::class);
        $this->expectExceptionMessage('does not appear to be a PHP binary');
        PhpBinaryPath::fromPhpBinaryPath(self::FAKE_PHP_EXECUTABLE);
    }

    public function testVersionFromCurrentProcess(): void
    {
        $phpBinary = PhpBinaryPath::fromCurrentProcess();

        self::assertSame(
            sprintf('%s.%s.%s', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION),
            $phpBinary->version(),
        );
        self::assertNull($phpBinary->phpConfigPath());
    }

    /**
     * @return array<string, array{0: non-empty-string, 1: non-empty-string}>
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public static function phpConfigPathProvider(): array
    {
        // data providers cannot return empty, even if the test is skipped
        if (Platform::isWindows()) {
            return ['skip' => ['skip', 'skip']];
        }

        $possiblePhpConfigPaths = array_filter(
            [
                ['/usr/bin/php-config8.3', '8.3'],
                ['/usr/bin/php-config8.2', '8.2'],
                ['/usr/bin/php-config8.1', '8.1'],
                ['/usr/bin/php-config8.0', '8.0'],
                ['/usr/bin/php-config7.4', '7.4'],
            ],
            static fn (array $phpConfigPath) => file_exists($phpConfigPath[0])
                && is_executable($phpConfigPath[0]),
        );

        return array_combine(
            array_column($possiblePhpConfigPaths, 0),
            $possiblePhpConfigPaths,
        );
    }

    #[DataProvider('phpConfigPathProvider')]
    public function testFromPhpConfigExecutable(string $phpConfigPath, string $expectedMajorMinor): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Do not need to test php-config on Windows as we are not building on Windows.');
        }

        assert($phpConfigPath !== '');
        $phpBinary = PhpBinaryPath::fromPhpConfigExecutable($phpConfigPath);

        self::assertSame(
            $expectedMajorMinor,
            $phpBinary->majorMinorVersion(),
        );

        self::assertSame($phpConfigPath, $phpBinary->phpConfigPath());
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

    public function testOperatingSystemFamily(): void
    {
        self::assertSame(
            OperatingSystemFamily::from(PHP_OS_FAMILY),
            PhpBinaryPath::fromCurrentProcess()
                ->operatingSystemFamily(),
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
        $phpBinary = PhpBinaryPath::fromCurrentProcess();

        $expectedExtensionDir = ini_get('extension_dir');

        // `extension_dir` may be a relative URL on Windows (e.g. "ext"), so resolve it according to the location of PHP
        if (! file_exists($expectedExtensionDir) || ! is_dir($expectedExtensionDir)) {
            $absoluteExtensionDir = dirname($phpBinary->phpBinaryPath) . DIRECTORY_SEPARATOR . $expectedExtensionDir;
            if (file_exists($absoluteExtensionDir) && is_dir($absoluteExtensionDir)) {
                $expectedExtensionDir = $absoluteExtensionDir;
            }
        }

        self::assertSame(
            $expectedExtensionDir,
            $phpBinary->extensionPath(),
        );
    }

    /**
     * @return array<string, array{0: string}>
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public static function phpPathProvider(): array
    {
        $possiblePhpBinaries = array_filter(
            array_unique([
                '/usr/bin/php',
                (string) (new PhpExecutableFinder())->find(),
                '/usr/bin/php8.4',
                '/usr/bin/php8.3',
                '/usr/bin/php8.2',
                '/usr/bin/php8.1',
                '/usr/bin/php8.0',
                '/usr/bin/php7.4',
                '/usr/bin/php7.3',
                '/usr/bin/php7.2',
                '/usr/bin/php7.1',
                '/usr/bin/php7.0',
                '/usr/bin/php5.6',
            ]),
            static fn (string $phpPath) => file_exists($phpPath) && is_executable($phpPath),
        );

        return array_combine(
            $possiblePhpBinaries,
            array_map(static fn (string $phpPath) => [$phpPath], $possiblePhpBinaries),
        );
    }

    #[DataProvider('phpPathProvider')]
    public function testDifferentVersionsOfPhp(string $phpPath): void
    {
        assert($phpPath !== '');
        $php = PhpBinaryPath::fromPhpBinaryPath($phpPath);
        self::assertArrayHasKey('Core', $php->extensions());
        self::assertNotEmpty($php->extensionPath());
        self::assertInstanceOf(OperatingSystem::class, $php->operatingSystem());
        self::assertNotEmpty($php->version());
        self::assertNotEmpty($php->majorMinorVersion());
        self::assertInstanceOf(Architecture::class, $php->machineType());
        self::assertGreaterThan(0, $php->phpIntSize());
        self::assertNotEmpty($php->phpinfo());
    }
}
