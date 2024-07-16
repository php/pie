<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
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
    public function testZipNames(): void
    {
        $platform = new TargetPlatform(
            OperatingSystem::Windows,
            PhpBinaryPath::fromCurrentProcess(),
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            WindowsCompiler::VC14,
        );
        $package  = new Package(
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            'phpf/foo',
            '1.2.3',
            null,
            [],
        );

        self::assertSame(
            [
                'php_foo-1.2.3-8.3-ts-vc14-x86_64.zip',
                'php_foo-1.2.3-8.3-vc14-ts-x86_64.zip',
            ],
            WindowsExtensionAssetName::zipNames($platform, $package),
        );
    }
}
