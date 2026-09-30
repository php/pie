<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\Update;

use Php\Pie\Platform\Architecture;
use Php\Pie\SelfManage\Update\PieAssetName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

#[CoversClass(PieAssetName::class)]
final class PieAssetNameTest extends TestCase
{
    public function testForRunningPieIsThePharWhenNotRunningStaticPhp(): void
    {
        self::assertSame(PieAssetName::Phar, PieAssetName::forRunningPie());
    }

    /** @return array<string, array{0: string, 1: Architecture, 2: PieAssetName}> */
    public static function supportedExecutablePlatformsProvider(): array
    {
        return [
            'Linux x86_64' => ['Linux', Architecture::x86_64, PieAssetName::LinuxX64],
            'Linux arm64' => ['Linux', Architecture::arm64, PieAssetName::LinuxArm64],
            'macOS arm64' => ['Darwin', Architecture::arm64, PieAssetName::MacOsArm64],
            'Windows x86_64' => ['Windows', Architecture::x86_64, PieAssetName::WindowsX64],
        ];
    }

    #[DataProvider('supportedExecutablePlatformsProvider')]
    public function testForExecutable(string $osFamily, Architecture $architecture, PieAssetName $expectedAssetName): void
    {
        self::assertSame($expectedAssetName, self::invokeForExecutable($osFamily, $architecture));
    }

    /** @return array<string, array{0: string, 1: Architecture}> */
    public static function unsupportedExecutablePlatformsProvider(): array
    {
        return [
            'macOS x86_64' => ['Darwin', Architecture::x86_64],
            'Windows arm64' => ['Windows', Architecture::arm64],
            'Linux x86' => ['Linux', Architecture::x86],
            'BSD x86_64' => ['BSD', Architecture::x86_64],
        ];
    }

    #[DataProvider('unsupportedExecutablePlatformsProvider')]
    public function testForExecutableOnUnsupportedPlatform(string $osFamily, Architecture $architecture): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('There is no PIE executable available for ' . $osFamily . ' ' . $architecture->name);

        self::invokeForExecutable($osFamily, $architecture);
    }

    /** @return array<string, array{0: PieAssetName, 1: PieAssetName}> */
    public static function nightlyProvider(): array
    {
        return [
            'PHAR' => [PieAssetName::Phar, PieAssetName::PharNightly],
            'PHAR nightly' => [PieAssetName::PharNightly, PieAssetName::PharNightly],
            'Linux x86_64' => [PieAssetName::LinuxX64, PieAssetName::LinuxX64],
            'Linux arm64' => [PieAssetName::LinuxArm64, PieAssetName::LinuxArm64],
            'macOS arm64' => [PieAssetName::MacOsArm64, PieAssetName::MacOsArm64],
            'Windows x86_64' => [PieAssetName::WindowsX64, PieAssetName::WindowsX64],
        ];
    }

    #[DataProvider('nightlyProvider')]
    public function testNightly(PieAssetName $assetName, PieAssetName $expectedNightlyAssetName): void
    {
        self::assertSame($expectedNightlyAssetName, $assetName->nightly());
    }

    private static function invokeForExecutable(string $osFamily, Architecture $architecture): mixed
    {
        return (new ReflectionMethod(PieAssetName::class, 'forExecutable'))->invoke(null, $osFamily, $architecture);
    }
}
