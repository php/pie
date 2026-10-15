<?php

declare(strict_types=1);

namespace Php\PieUnitTest;

use Php\Pie\ExtensionVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExtensionVersion::class)]
final class ExtensionVersionTest extends TestCase
{
    /** @return list<array{0: non-empty-string, 1: non-empty-string, 2: non-empty-string, 3: bool}> */
    public static function versionParsingProvider(): array
    {
        return [
            // Composer's normalizer worked without issue
            ['1.2.3', '1.2.3', '1.2.3.0', false],
            ['1.2.3.4', '1.2.3.4', '1.2.3.4', false],
            ['1.2', '1.2', '1.2.0.0', false],
            ['1', '1', '1.0.0.0', false],
            ['0', '0', '0.0.0.0', false],
            ['v1.2.3', 'v1.2.3', '1.2.3.0', false],
            ['2.1.0RC1', '2.1.0RC1', '2.1.0.0-RC1', false],
            ['2.0.0beta', '2.0.0beta', '2.0.0.0-beta', false],
            ['1.13.0-dev', '1.13.0-dev', '1.13.0.0-dev', false],
            ['3.4.0alpha2-dev', '3.4.0alpha2-dev', '3.4.0.0-alpha2-dev', false],
            ['1.2.3+abc', '1.2.3+abc', '1.2.3.0', false],
            ['1.2.3-alpha.34', '1.2.3-alpha.34', '1.2.3.0-alpha34', false],
            ['dev-main', 'dev-main', 'dev-main', false],
            ['20261015', '20261015', '20261015', false],
            // Fallback, best-guess parsers
            ['some-text', '0', '0.0.0.0', true],
            ['1.2.3-abcdef', '1.2.3', '1.2.3.0', true],
            ['8.3.6-1ubuntu1', '8.3.6', '8.3.6.0', true],
            ['7.4.33-nmm8', '7.4.33', '7.4.33.0', true],
            ['1.2-abc', '0', '0.0.0.0', true],
        ];
    }

    #[DataProvider('versionParsingProvider')]
    public function testParse(string $input, string $expectedOriginalOrModified, string $expectedNormalised, bool $expectedWasModified): void
    {
        $parsed = ExtensionVersion::parse($input);

        self::assertSame($input, $parsed->original);
        self::assertSame($expectedOriginalOrModified, $parsed->originalOrModified);
        self::assertSame($expectedNormalised, $parsed->normalised);
        self::assertSame($expectedWasModified, $parsed->wasModified());
    }
}
