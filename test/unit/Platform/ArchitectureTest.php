<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Php\Pie\Platform\Architecture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Architecture::class)]
final class ArchitectureTest extends TestCase
{
    /** @return array<non-empty-string, array{0: non-empty-string, 1: Architecture}> */
    public static function architectureMapProvider(): array
    {
        return [
            'x64' => ['x64', Architecture::x86_64],
            'x86_64' => ['x86_64', Architecture::x86_64],
            'AMD64' => ['AMD64', Architecture::x86_64],
            'arm64' => ['arm64', Architecture::arm64],
            'aarch64' => ['aarch64', Architecture::arm64],
            'x86' => ['x86', Architecture::x86],
            'something' => ['something', Architecture::x86],
        ];
    }

    /** @param non-empty-string $architectureString */
    #[DataProvider('architectureMapProvider')]
    public function testParseArchitecture(string $architectureString, Architecture $expectedArchitecture): void
    {
        self::assertSame($expectedArchitecture, Architecture::parseArchitecture($architectureString));
    }

    /** @return array<non-empty-string, array{0: Architecture, 1: non-empty-list<non-empty-string>}> */
    public static function allNamesProvider(): array
    {
        return [
            'x86_64' => [Architecture::x86_64, ['x86_64', 'x64']],
            'arm64' => [Architecture::arm64, ['arm64', 'aarch64']],
            'x86' => [Architecture::x86, ['x86']],
        ];
    }

    /** @param non-empty-list<non-empty-string> $expectedNames */
    #[DataProvider('allNamesProvider')]
    public function testAllNames(Architecture $architecture, array $expectedNames): void
    {
        self::assertSame($expectedNames, $architecture->allNames());
    }
}
