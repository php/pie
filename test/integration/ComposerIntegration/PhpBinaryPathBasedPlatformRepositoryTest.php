<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\ComposerIntegration;

use Composer\IO\NullIO;
use Composer\Util\Platform;
use Php\Pie\ComposerIntegration\PhpBinaryPathBasedPlatformRepository;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\Container;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\PkgConfig;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\PieIntegrationTest\Command\IsolatedWorkingDirectoryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Process\ExecutableFinder;

use function array_filter;
use function array_values;
use function count;
use function explode;
use function getenv;
use function Safe\file_get_contents;
use function Safe\realpath;
use function Safe\tempnam;
use function Safe\unlink;
use function sys_get_temp_dir;

use const PATH_SEPARATOR;

#[CoversClass(PhpBinaryPathBasedPlatformRepository::class)]
final class PhpBinaryPathBasedPlatformRepositoryTest extends IsolatedWorkingDirectoryTestCase
{
    private const PKG_CONFIG_RECORDING_INVOCATIONS_PATH = __DIR__ . '/../../assets/pkg-config-recording-invocations';

    public function testPkgConfigIsStartedNoMoreThanTwiceWhenBuildingPlatformRepository(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Bash script does not run on Windows.');
        }

        $realPkgConfig = (new ExecutableFinder())->find('pkg-config');
        if ($realPkgConfig === null) {
            self::markTestSkipped('pkg-config is not installed.');
        }

        $targetPlatform = TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null, null);
        $composer       = PieComposerFactory::createPieComposer(
            Container::factory(),
            new PieComposerRequest(new NullIO(), $targetPlatform, [], PieOperation::Resolve, [], false),
        );

        $invocationsLog = tempnam(sys_get_temp_dir(), 'pie_pkg_config_invocations_');
        $oldEnv         = $_ENV;

        $_ENV['PATH']                                = realpath(self::PKG_CONFIG_RECORDING_INVOCATIONS_PATH) . PATH_SEPARATOR . getenv('PATH');
        $_ENV['PIE_TEST_PKG_CONFIG_INVOCATIONS_LOG'] = $invocationsLog;
        $_ENV['PIE_TEST_REAL_PKG_CONFIG']            = $realPkgConfig;

        try {
            (new PhpBinaryPathBasedPlatformRepository($targetPlatform->phpBinaryPath, $composer, new InstalledPiePackages(), PkgConfig::detect(), []))->getPackages();

            $invocations = array_values(array_filter(explode("\n", file_get_contents($invocationsLog))));
        } finally {
            $_ENV = $oldEnv;
            unlink($invocationsLog);
        }

        self::assertNotEmpty($invocations);
        // We should only need to run pkg-config twice:
        // frist invocation `pkg-config --list-all`
        // second invocation `pkg-config --modversion <a> <b> <c>` for library that's present
        self::assertLessThanOrEqual(2, count($invocations));
    }
}
