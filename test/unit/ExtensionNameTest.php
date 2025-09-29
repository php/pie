<?php

declare(strict_types=1);

namespace Php\PieUnitTest;

use Composer\Package\CompletePackage;
use InvalidArgumentException;
use Php\Pie\ExtensionName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_combine;
use function array_map;

#[CoversClass(ExtensionName::class)]
final class ExtensionNameTest extends TestCase
{
    /** @return array<non-empty-string, array{0: non-empty-string, 1: non-empty-string}> */
    public static function validExtensionNamesProvider(): array
    {
        return [
            'ext-sodium' => ['ext-sodium', 'sodium'],
            'sodium' => ['sodium', 'sodium'],
        ];
    }

    #[DataProvider('validExtensionNamesProvider')]
    public function testValidExtensionNames(string $givenExtensionName, string $expectedNormalisedName): void
    {
        $extensionName = ExtensionName::normaliseFromString($givenExtensionName);

        self::assertSame($expectedNormalisedName, $extensionName->name());
        self::assertSame('ext-' . $expectedNormalisedName, $extensionName->nameWithExtPrefix());
    }

    /** @return array<string, array{0: string}> */
    public static function invalidExtensionNamesProvider(): array
    {
        $invalidExtensionNames = ['', 'kebab-case', 'money$ext'];

        return array_combine($invalidExtensionNames, array_map(static fn ($extensionName) => [$extensionName], $invalidExtensionNames));
    }

    #[DataProvider('invalidExtensionNamesProvider')]
    public function testInvalidExtensionNames(string $invalidExtensionName): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(<<<MESSAGE
The value "$invalidExtensionName" is not a valid extension name. An extension must start with a letter, and only contain alphanumeric characters or underscores
MESSAGE);

        ExtensionName::normaliseFromString($invalidExtensionName);
    }

    public function testFromComposerPackageWithPhpExtExtensionName(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['extension-name' => 'ext-something_else']);

        self::assertSame('something_else', ExtensionName::determineFromComposerPackage($composerCompletePackage)->name());
    }

    public function testFromComposerPackageWithInvalidPhpExtExtensionNameType(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['extension-name' => null]);

        self::assertSame('foo', ExtensionName::determineFromComposerPackage($composerCompletePackage)->name());
    }

    public function testFromComposerPackageWithEmptyPhpExtExtensionName(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['extension-name' => '']);

        self::assertSame('foo', ExtensionName::determineFromComposerPackage($composerCompletePackage)->name());
    }

    public function testFromComposerPackageWithoutPhpExtExtensionName(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['priority' => 80]);

        self::assertSame('foo', ExtensionName::determineFromComposerPackage($composerCompletePackage)->name());
    }

    public function testFromComposerPackageWithoutPhpExt(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');

        self::assertSame('foo', ExtensionName::determineFromComposerPackage($composerCompletePackage)->name());
    }
}
