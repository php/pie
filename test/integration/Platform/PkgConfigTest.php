<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Platform;

use Composer\Util\Platform;
use Php\Pie\Platform\PkgConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function getenv;
use function Safe\realpath;

use const PATH_SEPARATOR;

#[CoversClass(PkgConfig::class)]
final class PkgConfigTest extends TestCase
{
    private const FAKE_PKG_CONFIG_PATH    = __DIR__ . '/../../assets/fake-pkg-config';
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

    public function testVersionsOfAvailableLibrariesAreReturned(): void
    {
        $_ENV['PATH'] = realpath(self::FAKE_PKG_CONFIG_PATH) . PATH_SEPARATOR . getenv('PATH');

        self::assertSame(
            ['libcurl' => '8.12.1', 'libpng' => '1.6.47'],
            PkgConfig::detect()->versionsOf(['libcurl', 'libzip', 'libpng']),
        );
    }

    public function testOtherVersionsAreStillReturnedWhenOneLibraryCannotBeQueried(): void
    {
        $_ENV['PATH'] = realpath(self::FAKE_PKG_CONFIG_PATH) . PATH_SEPARATOR . getenv('PATH');

        self::assertSame(
            ['libcurl' => '8.12.1', 'libpng' => '1.6.47'],
            PkgConfig::detect()->versionsOf(['libcurl', 'broken', 'libpng']),
        );
    }

    public function testNoVersionsAreReturnedWhenPkgConfigIsNotAvailable(): void
    {
        $_ENV['PATH'] = realpath(self::PATH_WITHOUT_PKG_CONFIG);

        self::assertSame([], PkgConfig::detect()->versionsOf(['libcurl', 'libpng']));
    }
}
