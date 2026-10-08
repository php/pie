<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Building;

use Composer\Util\Platform;
use Php\Pie\Building\PcreCompilerFlags;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function getenv;
use function Safe\realpath;

use const PATH_SEPARATOR;

#[CoversClass(PcreCompilerFlags::class)]
final class PcreCompilerFlagsTest extends TestCase
{
    private const FAKE_PKG_CONFIG_PATH    = __DIR__ . '/../../assets/fake-pkg-config-pcre';
    private const PATH_WITHOUT_PKG_CONFIG = __DIR__;

    /** @var array<array-key, mixed> */
    private array $oldEnv;

    public function setUp(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Bash script does not run on Windows.');
        }

        $this->oldEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->oldEnv;
    }

    public function testFlagsFromPkgConfigAreReturnedWhenPhpUsesExternalPcre(): void
    {
        $_ENV['PATH']                 = realpath(self::FAKE_PKG_CONFIG_PATH) . PATH_SEPARATOR . getenv('PATH');
        $_ENV['PIE_TEST_PCRE_CFLAGS'] = '-I/opt/homebrew/Cellar/pcre2/10.49/include';

        self::assertSame(
            '-I/opt/homebrew/Cellar/pcre2/10.49/include',
            PcreCompilerFlags::forTargetPlatform($this->targetPlatformWherePhpUsesExternalPcre(true)),
        );
    }

    public function testNoFlagsWhenPhpUsesBundledPcre(): void
    {
        $_ENV['PATH']                 = realpath(self::FAKE_PKG_CONFIG_PATH) . PATH_SEPARATOR . getenv('PATH');
        $_ENV['PIE_TEST_PCRE_CFLAGS'] = '-I/opt/homebrew/Cellar/pcre2/10.49/include';

        self::assertNull(PcreCompilerFlags::forTargetPlatform($this->targetPlatformWherePhpUsesExternalPcre(false)));
    }

    public function testNoFlagsWhenPkgConfigReportsNone(): void
    {
        $_ENV['PATH']                 = realpath(self::FAKE_PKG_CONFIG_PATH) . PATH_SEPARATOR . getenv('PATH');
        $_ENV['PIE_TEST_PCRE_CFLAGS'] = '';

        self::assertNull(PcreCompilerFlags::forTargetPlatform($this->targetPlatformWherePhpUsesExternalPcre(true)));
    }

    public function testNoFlagsWhenPkgConfigIsNotAvailable(): void
    {
        $_ENV['PATH'] = realpath(self::PATH_WITHOUT_PKG_CONFIG);

        self::assertNull(PcreCompilerFlags::forTargetPlatform($this->targetPlatformWherePhpUsesExternalPcre(true)));
    }

    private function targetPlatformWherePhpUsesExternalPcre(bool $usesExternalPcre): TargetPlatform
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->method('usesExternalPcre')->willReturn($usesExternalPcre);

        return new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
            null,
        );
    }
}
