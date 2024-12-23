<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Composer\Package\CompletePackage;
use Php\Pie\DependencyResolver\Package;
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
            $this->createMock(CompletePackage::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'phpf/foo',
            '1.2.3',
            null,
            [],
            true,
            true,
            null,
            null,
            null,
            99,
        );
    }

    public function testZipNames(): void
    {
        self::assertSame(
            [
                'php_foo-1.2.3-' . $this->phpVersion . '-ts-vc14-x86_64.zip',
                'php_foo-1.2.3-' . $this->phpVersion . '-vc14-ts-x86_64.zip',
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
            ],
            WindowsExtensionAssetName::dllNames($this->platform, $this->package),
        );
    }
}
