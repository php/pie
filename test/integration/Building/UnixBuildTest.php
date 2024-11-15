<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Building;

use Composer\Package\CompletePackage;
use Composer\Util\Platform;
use Php\Pie\Building\UnixBuild;
use Php\Pie\ConfigureOption;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

#[CoversClass(UnixBuild::class)]
final class UnixBuildTest extends TestCase
{
    private const TEST_EXTENSION_PATH = __DIR__ . '/../../assets/pie_test_ext';

    public function testUnixBuildCanBuildExtension(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $output = new BufferedOutput();

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $this->createMock(CompletePackage::class),
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('pie_test_ext'),
                'pie_test_ext',
                '0.1.0',
                null,
                [ConfigureOption::fromComposerJsonDefinition(['name' => 'enable-pie_test_ext'])],
                true,
                true,
            ),
            self::TEST_EXTENSION_PATH,
        );

        $unixBuilder = new UnixBuild();
        $builtBinary = $unixBuilder->__invoke(
            $downloadedPackage,
            TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null),
            ['--enable-pie_test_ext'],
            $output,
        );

        self::assertNotEmpty($builtBinary);

        $outputString = $output->fetch();

        self::assertStringContainsString('phpize complete.', $outputString);
        self::assertStringContainsString('Configure complete with options: --enable-pie_test_ext', $outputString);
        self::assertStringContainsString('Build complete:', $outputString);
        self::assertStringContainsString('pie_test_ext.so', $outputString);

        self::assertSame(
            0,
            (new Process(['make', 'test'], $downloadedPackage->extractedSourcePath))
                ->mustRun()
                ->getExitCode(),
        );

        (new Process(['make', 'clean'], $downloadedPackage->extractedSourcePath))->mustRun();
        (new Process(['phpize', '--clean'], $downloadedPackage->extractedSourcePath))->mustRun();
    }
}
