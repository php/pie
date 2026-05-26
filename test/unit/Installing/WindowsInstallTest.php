<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing;

use Composer\IO\BufferIO;
use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\File\BinaryFile;
use Php\Pie\Installing\SetupIniFile;
use Php\Pie\Installing\WindowsInstall;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Platform\WindowsCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;

use function mkdir;
use function symlink;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

#[CoversClass(WindowsInstall::class)]
final class WindowsInstallTest extends TestCase
{
    public function testCopyExtraFileWithTraversalThrowsException(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('pie_test_', true);
        mkdir($tempDir);
        mkdir($tempDir . '/source');

        $setupIniFile = $this->createMock(SetupIniFile::class);
        $installer    = new WindowsInstall($setupIniFile);

        $package = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('test'),
            'foo/bar',
            '1.2.3',
            null,
        );

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath($package, $tempDir . '/source');

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        /** @phpstan-ignore property.notFound */
        (fn () => $this->phpBinaryPath = $tempDir . '/bin/php.exe')
            ->bindTo($phpBinaryPath, PhpBinaryPath::class)();
        $phpBinaryPath->method('majorMinorVersion')->willReturn('8.5');
        $phpBinaryPath->method('extensionPath')->willReturn($tempDir . '/ext');
        mkdir($tempDir . '/ext', 0777, true);

        $targetPlatform = new TargetPlatform(
            OperatingSystem::Windows,
            OperatingSystemFamily::Windows,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            WindowsCompiler::VS16,
            null,
        );

        mkdir($tempDir . '/bin', 0777, true);
        touch($tempDir . '/bin/php.exe');

        $file = $this->createMock(SplFileInfo::class);
        $file->method('getPathname')->willReturn($tempDir . '/source/../escape.txt');

        $reflection = new ReflectionClass(WindowsInstall::class);
        $method     = $reflection->getMethod('copyExtraFile');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to copy extra file with traversal segment');

        $method->invoke($installer, $targetPlatform, $downloadedPackage, $file);
    }

    public function testCopyExtraFileWithDestinationEscapeThrowsException(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('pie_test_', true);
        mkdir($tempDir);
        mkdir($tempDir . '/source');
        mkdir($tempDir . '/bin', 0777, true);
        touch($tempDir . '/bin/php.exe');

        $setupIniFile = $this->createMock(SetupIniFile::class);
        $installer    = new WindowsInstall($setupIniFile);

        $package = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('test'),
            'foo/bar',
            '1.2.3',
            null,
        );

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath($package, $tempDir . '/source');

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        /** @phpstan-ignore property.notFound */
        (fn () => $this->phpBinaryPath = $tempDir . '/bin/php.exe')
            ->bindTo($phpBinaryPath, PhpBinaryPath::class)();
        $phpBinaryPath->method('majorMinorVersion')->willReturn('8.5');
        $phpBinaryPath->method('extensionPath')->willReturn($tempDir . '/ext');
        mkdir($tempDir . '/ext', 0777, true);

        $targetPlatform = new TargetPlatform(
            OperatingSystem::Windows,
            OperatingSystemFamily::Windows,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            WindowsCompiler::VS16,
            null,
        );

        $extrasRoot = $tempDir . '/bin/extras/test';
        mkdir($extrasRoot, 0777, true);
        mkdir($tempDir . '/outside');
        symlink($tempDir . '/outside', $extrasRoot . '/escape');

        $file = $this->createMock(SplFileInfo::class);
        $file->method('getPathname')->willReturn($tempDir . '/source/escape/file.txt');

        $reflection = new ReflectionClass(WindowsInstall::class);
        $method     = $reflection->getMethod('copyExtraFile');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('escapes extras root');

        $method->invoke($installer, $targetPlatform, $downloadedPackage, $file);
    }

    public function testInvokeSkipsSymlinks(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('pie_test_', true);
        mkdir($tempDir);
        mkdir($tempDir . '/source');
        mkdir($tempDir . '/outside');
        touch($tempDir . '/outside/danger.txt');
        symlink($tempDir . '/outside/danger.txt', $tempDir . '/source/danger.link');

        $setupIniFile = $this->createMock(SetupIniFile::class);
        $installer    = new WindowsInstall($setupIniFile);

        $package = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('test'),
            'foo/bar',
            '1.2.3',
            null,
        );

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath($package, $tempDir . '/source');

        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        /** @phpstan-ignore property.notFound */
        (fn () => $this->phpBinaryPath = $tempDir . '/bin/php.exe')
            ->bindTo($phpBinaryPath, PhpBinaryPath::class)();
        $phpBinaryPath->method('majorMinorVersion')->willReturn('8.5');
        $phpBinaryPath->method('extensionPath')->willReturn($tempDir . '/ext');
        mkdir($tempDir . '/ext', 0777, true);

        $targetPlatform = new TargetPlatform(
            OperatingSystem::Windows,
            OperatingSystemFamily::Windows,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            WindowsCompiler::VS16,
            null,
        );

        $builtFilename = $tempDir . '/source/php_test-1.2.3-8.5-ts-vs16-x86_64.dll';
        touch($builtFilename);
        $builtBinary = BinaryFile::fromFileWithSha256Checksum($builtFilename);

        $output = new BufferIO();

        $installer->__invoke($downloadedPackage, $targetPlatform, $builtBinary, $output, false);

        self::assertStringNotContainsString('danger.link', $output->getOutput());
    }
}
