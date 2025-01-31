<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Building;

use Composer\Package\CompletePackage;
use Composer\Util\Platform;
use Php\Pie\Building\ExtensionBinaryNotFound;
use Php\Pie\Building\UnixBuild;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function dirname;

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
            ),
            self::TEST_EXTENSION_PATH,
        );

        $unixBuilder = new UnixBuild();
        $builtBinary = $unixBuilder->__invoke(
            $downloadedPackage,
            TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null),
            ['--enable-pie_test_ext'],
            $output,
            null,
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

    public function testUnixBuildWillThrowExceptionWhenExpectedBinaryNameMismatches(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $output = new BufferedOutput();

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $this->createMock(CompletePackage::class),
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('mismatched_name'),
                'pie_test_ext',
                '0.1.0',
                null,
            ),
            self::TEST_EXTENSION_PATH,
        );

        $unixBuilder = new UnixBuild();

        $this->expectException(ExtensionBinaryNotFound::class);
        try {
            $unixBuilder->__invoke(
                $downloadedPackage,
                TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null),
                ['--enable-pie_test_ext'],
                $output,
                null,
            );
        } finally {
            (new Process(['make', 'clean'], $downloadedPackage->extractedSourcePath))->mustRun();
            (new Process(['phpize', '--clean'], $downloadedPackage->extractedSourcePath))->mustRun();
        }
    }

    public function testUnixBuildCanBuildExtensionWithBuildPath(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $output = new BufferedOutput();

        $composerPackage = $this->createMock(CompletePackage::class);
        $composerPackage->method('getPrettyName')->willReturn('myvendor/pie_test_ext');
        $composerPackage->method('getPrettyVersion')->willReturn('0.1.0');
        $composerPackage->method('getType')->willReturn('php-ext');
        $composerPackage->method('getPhpExt')->willReturn(['build-path' => 'pie_test_ext']);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            Package::fromComposerCompletePackage($composerPackage),
            dirname(self::TEST_EXTENSION_PATH),
        );

        $unixBuilder = new UnixBuild();
        $builtBinary = $unixBuilder->__invoke(
            $downloadedPackage,
            TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null),
            ['--enable-pie_test_ext'],
            $output,
            null,
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

    public function testCleanupDoesNotCleanWhenConfigureIsMissing(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        (new Process(['phpize', '--clean'], self::TEST_EXTENSION_PATH))->mustRun();
        self::assertFileDoesNotExist(self::TEST_EXTENSION_PATH . '/configure');

        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $this->createMock(CompletePackage::class),
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('pie_test_ext'),
                'pie_test_ext',
                '0.1.0',
                null,
            ),
            self::TEST_EXTENSION_PATH,
        );

        $unixBuilder = new UnixBuild();
        $unixBuilder->__invoke(
            $downloadedPackage,
            TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null),
            ['--enable-pie_test_ext'],
            $output,
            null,
        );

        $outputString = $output->fetch();
        self::assertStringContainsString('Skipping phpize --clean, configure does not exist', $outputString);
        self::assertStringNotContainsString('Build files cleaned up', $outputString);
    }

    public function testVerboseOutputShowsCleanupMessages(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        (new Process(['phpize'], self::TEST_EXTENSION_PATH))->mustRun();
        self::assertFileExists(self::TEST_EXTENSION_PATH . '/configure');

        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $this->createMock(CompletePackage::class),
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('pie_test_ext'),
                'pie_test_ext',
                '0.1.0',
                null,
            ),
            self::TEST_EXTENSION_PATH,
        );

        $unixBuilder = new UnixBuild();
        $unixBuilder->__invoke(
            $downloadedPackage,
            TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null),
            ['--enable-pie_test_ext'],
            $output,
            null,
        );

        $outputString = $output->fetch();
        self::assertStringContainsString('Running phpize --clean step', $outputString);
        self::assertStringContainsString('Build files cleaned up', $outputString);
    }
}
