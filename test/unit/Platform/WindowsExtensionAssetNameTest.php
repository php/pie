<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Platform\WindowsCompiler;
use Php\Pie\Platform\WindowsExtensionAssetName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

#[CoversClass(WindowsExtensionAssetName::class)]
final class WindowsExtensionAssetNameTest extends TestCase
{
    private TargetPlatform $platform;
    private Package $package;
    private string $phpVersion;

    public function setUp(): void
    {
        parent::setUp();

        $this->platform = new TargetPlatform(
            OperatingSystem::Windows,
            OperatingSystemFamily::Windows,
            PhpBinaryPath::fromCurrentProcess(),
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            WindowsCompiler::VC14,
        );

        $this->phpVersion = $this->platform->phpBinaryPath->majorMinorVersion();

        $this->package = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'phpf/foo',
            '1.2.3',
            null,
        );
    }

    public function testZipNames(): void
    {
        self::assertSame(
            [
                'php_foo-1.2.3-' . $this->phpVersion . '-ts-vc14-x86_64.zip',
                'php_foo-1.2.3-' . $this->phpVersion . '-vc14-ts-x86_64.zip',
                'php_foo-1.2.3-' . $this->phpVersion . '-ts-vc14-x64.zip',
                'php_foo-1.2.3-' . $this->phpVersion . '-vc14-ts-x64.zip',
            ],
            WindowsExtensionAssetName::zipNames($this->platform, $this->package),
        );
    }

    public function testDllNames(): void
    {
        self::assertSame(
            [
                'php_foo-1.2.3-' . $this->phpVersion . '-ts-vc14-x86_64.dll',
                'php_foo-1.2.3-' . $this->phpVersion . '-vc14-ts-x86_64.dll',
                'php_foo-1.2.3-' . $this->phpVersion . '-ts-vc14-x64.dll',
                'php_foo-1.2.3-' . $this->phpVersion . '-vc14-ts-x64.dll',
            ],
            WindowsExtensionAssetName::dllNames($this->platform, $this->package),
        );
    }

    public function testVersionWithVPrefixGeneratesVariantsWithAndWithoutPrefix(): void
    {
        $packageWithV = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'phpf/foo',
            'v1.2.3',
            null,
        );

        $names = WindowsExtensionAssetName::zipNames($this->platform, $packageWithV);

        // Should contain both "v1.2.3" and "1.2.3" variants
        self::assertContains('php_foo-v1.2.3-' . $this->phpVersion . '-ts-vc14-x86_64.zip', $names);
        self::assertContains('php_foo-1.2.3-' . $this->phpVersion . '-ts-vc14-x86_64.zip', $names);
        self::assertContains('php_foo-v1.2.3-' . $this->phpVersion . '-ts-vc14-x64.zip', $names);
        self::assertContains('php_foo-1.2.3-' . $this->phpVersion . '-ts-vc14-x64.zip', $names);
    }

    public function testX86ArchitectureDoesNotDuplicate(): void
    {
        $x86Platform = new TargetPlatform(
            OperatingSystem::Windows,
            OperatingSystemFamily::Windows,
            PhpBinaryPath::fromCurrentProcess(),
            Architecture::x86,
            ThreadSafetyMode::ThreadSafe,
            1,
            WindowsCompiler::VC14,
        );

        $names = WindowsExtensionAssetName::zipNames($x86Platform, $this->package);

        // x86 has only one name in allNames(), so no arch duplicates
        self::assertSame(
            [
                'php_foo-1.2.3-' . $this->phpVersion . '-ts-vc14-x86.zip',
                'php_foo-1.2.3-' . $this->phpVersion . '-vc14-ts-x86.zip',
            ],
            $names,
        );
    }

    public function testDetermineDllNameFallsBackToSimpleName(): void
    {
        // Create a temp directory with only a simple-named DLL (as downloads.php.net provides)
        $tempDir = sys_get_temp_dir() . '/' . uniqid('pie_test_', true);
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/php_foo.dll', 'fake dll content');

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            $this->package,
            $tempDir,
        );

        $result = WindowsExtensionAssetName::determineDllName($this->platform, $downloadedPackage);
        self::assertStringEndsWith('php_foo.dll', $result);
    }

    public function testDetermineDllNameThrowsWhenNoDllFound(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('pie_test_empty_', true);
        mkdir($tempDir, 0777, true);

        $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
            $this->package,
            $tempDir,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('php_foo.dll');
        WindowsExtensionAssetName::determineDllName($this->platform, $downloadedPackage);
    }
}
