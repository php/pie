<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\BuildTools;

use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPhp\PhpizePath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\SelfManage\BuildTools\PhpizeBuildToolFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;

use function getenv;
use function Safe\putenv;
use function Safe\realpath;

use const DIRECTORY_SEPARATOR;

#[RequiresOperatingSystemFamily('Linux')]
#[CoversClass(PhpizeBuildToolFinder::class)]
final class PhpizeBuildToolFinderTest extends TestCase
{
    private const GOOD_PHPIZE_PATH = __DIR__ . '/../../../assets/phpize/good';
    private const BAD_PHPIZE_PATH  = __DIR__ . '/../../../assets/phpize/bad';

    public function testCheckWithPhpizeInPath(): void
    {
        $oldPath = getenv('PATH');
        putenv('PATH=' . realpath(self::GOOD_PHPIZE_PATH));

        $mockPhpBinary = $this->createMock(PhpBinaryPath::class);
        $mockPhpBinary->method('phpApiVersion')->willReturn('20240924');
        (fn () => $this->phpBinaryPath = '/path/to/php')
            ->bindTo($mockPhpBinary, PhpBinaryPath::class)();

        self::assertTrue((new PhpizeBuildToolFinder([]))->check(new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $mockPhpBinary,
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
            null,
        )));

        putenv('PATH=' . $oldPath);
    }

    public function testCheckWithPhpizeFromTargetPlatform(): void
    {
        $oldPath = getenv('PATH');
        putenv('PATH=' . realpath(self::BAD_PHPIZE_PATH));

        $mockPhpBinary = $this->createMock(PhpBinaryPath::class);
        $mockPhpBinary->method('phpApiVersion')->willReturn('20240924');
        (fn () => $this->phpBinaryPath = '/path/to/php')
            ->bindTo($mockPhpBinary, PhpBinaryPath::class)();

        $goodPhpize = realpath(self::GOOD_PHPIZE_PATH . DIRECTORY_SEPARATOR . 'phpize');

        self::assertTrue((new PhpizeBuildToolFinder([]))->check(new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $mockPhpBinary,
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
            new PhpizePath($goodPhpize),
        )));

        putenv('PATH=' . $oldPath);
    }

    public function testCheckWithPhpizeGuessed(): void
    {
        $oldPath = getenv('PATH');
        putenv('PATH=' . realpath(self::BAD_PHPIZE_PATH));

        $mockPhpBinary = $this->createMock(PhpBinaryPath::class);
        $mockPhpBinary->method('phpApiVersion')->willReturn('20240924');
        $phpPath = realpath(self::GOOD_PHPIZE_PATH . DIRECTORY_SEPARATOR . 'php');
        (fn () => $this->phpBinaryPath = $phpPath)
            ->bindTo($mockPhpBinary, PhpBinaryPath::class)();

        self::assertTrue((new PhpizeBuildToolFinder([]))->check(new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $mockPhpBinary,
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
            null,
        )));

        putenv('PATH=' . $oldPath);
    }

    public function testPhpizeForDifferentPhpApiVersionIsRejected(): void
    {
        $oldPath = getenv('PATH');
        putenv('PATH=' . realpath(self::GOOD_PHPIZE_PATH));

        $mockPhpBinary = $this->createMock(PhpBinaryPath::class);
        // This should not be any API version of a real PHP, otherwise this test might pick up a wrong phpize and false-positive :D
        $mockPhpBinary->method('phpApiVersion')->willReturn('30250925');
        (fn () => $this->phpBinaryPath = '/path/to/php')
            ->bindTo($mockPhpBinary, PhpBinaryPath::class)();

        $goodPhpize = realpath(self::GOOD_PHPIZE_PATH . DIRECTORY_SEPARATOR . 'phpize');

        self::assertFalse((new PhpizeBuildToolFinder([]))->check(new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $mockPhpBinary,
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
            new PhpizePath($goodPhpize),
        )));

        putenv('PATH=' . $oldPath);
    }
}
