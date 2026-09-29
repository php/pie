<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Building;

use Composer\IO\BufferIO;
use Composer\Package\CompletePackageInterface;
use Composer\Util\Platform;
use Php\Pie\Building\ExtensionBinaryNotFound;
use Php\Pie\Building\UnixBuild;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Downloading\DownloadUrlMethod;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function dirname;

#[CoversClass(UnixBuild::class)]
final class UnixBuildTest extends TestCase
{
    private const COMPOSER_PACKAGE_EXTRA_KEY              = 'download-url-method';
    private const TEST_EXTENSION_PATH                     = __DIR__ . '/../../assets/pie_test_ext';
    private const FAKE_PHP_CONFIG_CONFIGURE_OPTIONS_PROXY = __DIR__ . '/../../assets/fake-php-config-configure-options-proxy.sh';
    private const TEST_PREBUILT_PATH_VALID                = __DIR__ . '/../../assets/pre-packaged-binary-examples/valid';
    private const TEST_PREBUILT_PATH_INVALID              = __DIR__ . '/../../assets/pre-packaged-binary-examples/invalid';

    public function testUnixSourceBuildCanBuildExtension(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $output = new BufferIO();

        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage
            ->method('getExtra')
            ->willReturn([self::COMPOSER_PACKAGE_EXTRA_KEY => DownloadUrlMethod::ComposerDefaultDownload->value]);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $composerPackage,
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
            TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null, null),
            ['--enable-pie_test_ext'],
            $output,
        );

        self::assertNotEmpty($builtBinary);

        $outputString = $output->getOutput();

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

    public function testUnixSourceBuildWillThrowExceptionWhenExpectedBinaryNameMismatches(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $output = new BufferIO();

        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage
            ->method('getExtra')
            ->willReturn([self::COMPOSER_PACKAGE_EXTRA_KEY => DownloadUrlMethod::ComposerDefaultDownload->value]);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $composerPackage,
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
                TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null, null),
                ['--enable-pie_test_ext'],
                $output,
            );
        } finally {
            (new Process(['make', 'clean'], $downloadedPackage->extractedSourcePath))->mustRun();
            (new Process(['phpize', '--clean'], $downloadedPackage->extractedSourcePath))->mustRun();
        }
    }

    public function testUnixSourceBuildCanBuildExtensionWithBuildPath(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $output = new BufferIO();

        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage->method('getPrettyName')->willReturn('myvendor/pie_test_ext');
        $composerPackage->method('getPrettyVersion')->willReturn('0.1.0');
        $composerPackage->method('getType')->willReturn('php-ext');
        $composerPackage->method('getPhpExt')->willReturn(['build-path' => 'pie_test_ext']);
        $composerPackage
            ->method('getExtra')
            ->willReturn([self::COMPOSER_PACKAGE_EXTRA_KEY => DownloadUrlMethod::ComposerDefaultDownload->value]);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            Package::fromComposerCompletePackage($composerPackage),
            dirname(self::TEST_EXTENSION_PATH),
        );

        $unixBuilder = new UnixBuild();
        $builtBinary = $unixBuilder->__invoke(
            $downloadedPackage,
            TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null, null),
            ['--enable-pie_test_ext'],
            $output,
        );

        self::assertNotEmpty($builtBinary);

        $outputString = $output->getOutput();

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

    public function testUnixBinaryBuildThrowsErrorWhenBinaryFileNotFound(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $output = new BufferIO();

        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage->method('getPrettyName')->willReturn('myvendor/pie_test_ext');
        $composerPackage->method('getPrettyVersion')->willReturn('0.1.0');
        $composerPackage->method('getType')->willReturn('php-ext');
        $composerPackage
            ->method('getExtra')
            ->willReturn([self::COMPOSER_PACKAGE_EXTRA_KEY => DownloadUrlMethod::PrePackagedBinary->value]);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            Package::fromComposerCompletePackage($composerPackage),
            self::TEST_PREBUILT_PATH_INVALID,
        );

        $targetPlatform = TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null, null);
        $unixBuilder    = new UnixBuild();

        $this->expectException(ExtensionBinaryNotFound::class);
        $this->expectExceptionMessage('Expected pre-packaged binary does not exist');
        $unixBuilder->__invoke(
            $downloadedPackage,
            $targetPlatform,
            ['--enable-pie_test_ext'],
            $output,
        );
    }

    public function testUnixBinaryBuildReturnsBinaryFile(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $output = new BufferIO();

        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage->method('getPrettyName')->willReturn('myvendor/pie_test_ext');
        $composerPackage->method('getPrettyVersion')->willReturn('0.1.0');
        $composerPackage->method('getType')->willReturn('php-ext');
        $composerPackage
            ->method('getExtra')
            ->willReturn([self::COMPOSER_PACKAGE_EXTRA_KEY => DownloadUrlMethod::PrePackagedBinary->value]);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            Package::fromComposerCompletePackage($composerPackage),
            self::TEST_PREBUILT_PATH_VALID,
        );

        $targetPlatform = TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null, null);
        $unixBuilder    = new UnixBuild();

        $binaryFile = $unixBuilder->__invoke(
            $downloadedPackage,
            $targetPlatform,
            ['--enable-pie_test_ext'],
            $output,
        );

        self::assertSame('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $binaryFile->checksum);
        self::assertStringEndsWith('pre-packaged-binary-examples/valid/pie_test_ext.so', $binaryFile->filePath);
    }

    public function testCleanupDoesNotCleanWhenConfigureIsMissing(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        (new Process(['phpize', '--clean'], self::TEST_EXTENSION_PATH))->mustRun();
        self::assertFileDoesNotExist(self::TEST_EXTENSION_PATH . '/configure');

        $output = new BufferIO(verbosity: OutputInterface::VERBOSITY_VERBOSE);

        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage
            ->method('getExtra')
            ->willReturn([self::COMPOSER_PACKAGE_EXTRA_KEY => DownloadUrlMethod::ComposerDefaultDownload->value]);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $composerPackage,
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
            TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null, null),
            ['--enable-pie_test_ext'],
            $output,
        );

        $outputString = $output->getOutput();
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

        $output = new BufferIO(verbosity: OutputInterface::VERBOSITY_VERBOSE);

        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage
            ->method('getExtra')
            ->willReturn([self::COMPOSER_PACKAGE_EXTRA_KEY => DownloadUrlMethod::ComposerDefaultDownload->value]);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $composerPackage,
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
            TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess(), null, null),
            ['--enable-pie_test_ext'],
            $output,
        );

        $outputString = $output->getOutput();
        self::assertStringContainsString('Running phpize --clean step', $outputString);
        self::assertStringContainsString('Build files cleaned up', $outputString);
    }

    private function useFakeConfigureOptions(string $configureOptions): void
    {
        $realPhpConfig = PhpBinaryPath::fromCurrentProcess()->phpConfigPath();
        if ($realPhpConfig === null) {
            self::markTestSkipped('No system php-config found to build a wrapper from.');
        }

        $_ENV['PIE_TEST_FAKE_CONFIGURE_OPTIONS'] = $configureOptions;
        $_ENV['PIE_TEST_REAL_PHP_CONFIG']        = $realPhpConfig;
    }

    /** @param list<non-empty-string> $configureOptions */
    private function buildPieTestExtAndCaptureOutput(array $configureOptions): string
    {
        $output = new BufferIO();

        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage
            ->method('getExtra')
            ->willReturn([self::COMPOSER_PACKAGE_EXTRA_KEY => DownloadUrlMethod::ComposerDefaultDownload->value]);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            new Package(
                $composerPackage,
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('pie_test_ext'),
                'pie_test_ext',
                '0.1.0',
                null,
            ),
            self::TEST_EXTENSION_PATH,
        );

        (new UnixBuild())->__invoke(
            $downloadedPackage,
            TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromPhpConfigExecutable(self::FAKE_PHP_CONFIG_CONFIGURE_OPTIONS_PROXY), null, null),
            $configureOptions,
            $output,
        );

        (new Process(['make', 'clean'], $downloadedPackage->extractedSourcePath))->mustRun();
        (new Process(['phpize', '--clean'], $downloadedPackage->extractedSourcePath))->mustRun();

        return $output->getOutput();
    }

    public function testWithLibdirIsAutoDetectedFromPhpConfig(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $this->useFakeConfigureOptions('--prefix=/usr --with-libdir=lib64');
        $outputString = $this->buildPieTestExtAndCaptureOutput(['--enable-pie_test_ext']);

        self::assertStringContainsString('Configure complete with options: --enable-pie_test_ext --with-libdir=lib64', $outputString);
    }

    public function testWithLibdirIsNotOverriddenWhenAlreadyProvided(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $this->useFakeConfigureOptions('--prefix=/usr --with-libdir=lib64');
        $outputString = $this->buildPieTestExtAndCaptureOutput(['--enable-pie_test_ext', '--with-libdir=custom']);

        self::assertStringContainsString('Configure complete with options: --enable-pie_test_ext --with-libdir=custom', $outputString);
        self::assertStringNotContainsString('lib64', $outputString);
    }
}
