<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Installing;

use Composer\IO\BufferIO;
use Composer\Package\CompletePackageInterface;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use Php\Pie\Building\UnixBuild;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Downloading\DownloadUrlMethod;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\File\BinaryFile;
use Php\Pie\Installing\Ini\PickBestSetupIniApproach;
use Php\Pie\Installing\SetupIniFile;
use Php\Pie\Installing\UnixInstall;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

use function array_combine;
use function array_filter;
use function array_map;
use function array_unshift;
use function assert;
use function file_exists;
use function getenv;
use function is_executable;
use function is_writable;
use function Safe\mkdir;
use function Safe\putenv;
use function Safe\rename;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(UnixInstall::class)]
final class UnixInstallTest extends TestCase
{
    private const COMPOSER_PACKAGE_EXTRA_KEY = 'download-url-method';
    private const TEST_EXTENSION_PATH        = __DIR__ . '/../../assets/pie_test_ext';
    private const TEST_PREBUILT_PATH         = __DIR__ . '/../../assets/pre-packaged-binary-examples/install';

    /** @return array<string, array{0: non-empty-string}> */
    public static function phpPathProvider(): array
    {
        // data providers cannot return empty, even if the test is skipped
        if (Platform::isWindows()) {
            return ['skip' => ['skip']];
        }

        $possiblePhpConfigPaths = array_filter(
            [
                '/usr/bin/php-config',
                '/usr/bin/php-config8.5',
                '/usr/bin/php-config8.4',
                '/usr/bin/php-config8.3',
                '/usr/bin/php-config8.2',
                '/usr/bin/php-config8.1',
                '/usr/bin/php-config8.0',
                '/usr/bin/php-config7.4',
                '/usr/bin/php-config7.3',
                '/usr/bin/php-config7.2',
            ],
            static fn (string $phpConfigPath) => file_exists($phpConfigPath)
                && is_executable($phpConfigPath),
        );

        return array_combine(
            $possiblePhpConfigPaths,
            array_map(static fn (string $phpConfigPath) => [$phpConfigPath], $possiblePhpConfigPaths),
        );
    }

    #[DataProvider('phpPathProvider')]
    public function testUnixInstallCanInstallExtensionBuiltFromSource(string $phpConfig): void
    {
        $installRoot    = '/tmp/' . uniqid('pie-test-install-root-', true);
        $oldInstallRoot = getenv('INSTALL_ROOT');
        putenv('INSTALL_ROOT=' . $installRoot);

        assert($phpConfig !== '');
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $output         = new BufferIO();
        $targetPlatform = TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromPhpConfigExecutable($phpConfig), null, null);
        $extensionPath  = $targetPlatform->phpBinaryPath->extensionPath($installRoot);

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

        $built = (new UnixBuild())->__invoke(
            $downloadedPackage,
            $targetPlatform,
            ['--enable-pie_test_ext'],
            $output,
        );

        $installedSharedObject = (new UnixInstall(new SetupIniFile(new PickBestSetupIniApproach([]))))->__invoke(
            $downloadedPackage,
            $targetPlatform,
            $built,
            $output,
            true,
        );
        $outputString          = $output->getOutput();

        self::assertStringContainsString('Install complete: ' . $extensionPath . '/pie_test_ext.so', $outputString);
        self::assertStringContainsString('You must now add "extension=pie_test_ext" to your php.ini', $outputString);

        self::assertSame($extensionPath . '/pie_test_ext.so', $installedSharedObject->filePath);
        self::assertFileExists($installedSharedObject->filePath);

        $rmCommand = ['rm', $installedSharedObject->filePath];
        if (! is_writable($installedSharedObject->filePath)) {
            array_unshift($rmCommand, 'sudo');
        }

        (new Process($rmCommand))->mustRun();
        (new Process(['make', 'clean'], $downloadedPackage->extractedSourcePath))->mustRun();
        (new Process(['phpize', '--clean'], $downloadedPackage->extractedSourcePath))->mustRun();
        (new Filesystem())->remove($installRoot);
        putenv('INSTALL_ROOT=' . $oldInstallRoot);
    }

    #[DataProvider('phpPathProvider')]
    public function testUnixInstallCanInstallPrePackagedBinary(string $phpConfig): void
    {
        $installRoot    = '/tmp/' . uniqid('pie-test-install-root-', true);
        $oldInstallRoot = getenv('INSTALL_ROOT');
        putenv('INSTALL_ROOT=' . $installRoot);

        assert($phpConfig !== '');
        if (Platform::isWindows()) {
            self::markTestSkipped('Unix build test cannot be run on Windows');
        }

        $output         = new BufferIO();
        $targetPlatform = TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromPhpConfigExecutable($phpConfig), null, null);
        $extensionPath  = $installRoot . $targetPlatform->phpBinaryPath->extensionPath();
        mkdir($extensionPath, 0777, true);

        // First build it (otherwise the test assets would need to have a binary for every test platform...)
        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage
            ->method('getExtra')
            ->willReturn([self::COMPOSER_PACKAGE_EXTRA_KEY => DownloadUrlMethod::ComposerDefaultDownload->value]);

        $built = (new UnixBuild())->__invoke(
            DownloadedPackage::fromPackageAndExtractedPath(
                new Package(
                    $composerPackage,
                    ExtensionType::PhpModule,
                    ExtensionName::normaliseFromString('pie_test_ext'),
                    'pie_test_ext',
                    '0.1.0',
                    null,
                ),
                self::TEST_EXTENSION_PATH,
            ),
            $targetPlatform,
            ['--enable-pie_test_ext'],
            $output,
        );

        if (! file_exists(self::TEST_PREBUILT_PATH)) {
            mkdir(self::TEST_PREBUILT_PATH, 0777, true);
        }

        /**
         * Move the built .so into a new path; this simulates a pre-packaged binary, which would not have Makefile etc
         * so this ensures we're not accidentally relying on any build mechanism (`make install` or otherwise)
         */
        $prebuiltBinaryFilePath = self::TEST_PREBUILT_PATH . DIRECTORY_SEPARATOR . 'pie_test_ext.so';
        rename($built->filePath, $prebuiltBinaryFilePath);

        $prebuiltBinaryFile = BinaryFile::fromFileWithSha256Checksum($prebuiltBinaryFilePath);

        $composerPackage = $this->createMock(CompletePackageInterface::class);
        $composerPackage
            ->method('getExtra')
            ->willReturn([self::COMPOSER_PACKAGE_EXTRA_KEY => DownloadUrlMethod::PrePackagedBinary->value]);

        $installedSharedObject = (new UnixInstall(new SetupIniFile(new PickBestSetupIniApproach([]))))->__invoke(
            DownloadedPackage::fromPackageAndExtractedPath(
                new Package(
                    $composerPackage,
                    ExtensionType::PhpModule,
                    ExtensionName::normaliseFromString('pie_test_ext'),
                    'pie_test_ext',
                    '0.1.0',
                    null,
                ),
                self::TEST_PREBUILT_PATH,
            ),
            $targetPlatform,
            $prebuiltBinaryFile,
            $output,
            true,
        );
        $outputString          = $output->getOutput();

        self::assertStringContainsString('Install complete: ' . $extensionPath . '/pie_test_ext.so', $outputString);
        self::assertStringContainsString('You must now add "extension=pie_test_ext" to your php.ini', $outputString);

        self::assertSame($extensionPath . '/pie_test_ext.so', $installedSharedObject->filePath);
        self::assertFileExists($installedSharedObject->filePath);

        $rmCommand = ['rm', $installedSharedObject->filePath];
        if (! is_writable($installedSharedObject->filePath)) {
            array_unshift($rmCommand, 'sudo');
        }

        (new Process($rmCommand))->mustRun();
        (new Filesystem())->remove($prebuiltBinaryFile->filePath);
        (new Filesystem())->remove($installRoot);
        putenv('INSTALL_ROOT=' . $oldInstallRoot);
    }
}
