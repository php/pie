<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\InstallForPhpProject;

use Php\Pie\ExtensionName;
use Php\Pie\Installing\InstallForPhpProject\ExtensionToPackageSelections;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExtensionToPackageSelections::class)]
final class ExtensionToPackageSelectionsTest extends TestCase
{
    public function testFromArrayMap(): void
    {
        $selections = ExtensionToPackageSelections::fromArrayMap([
            'foo' => 'foo/foo',
            'bar' => 'bat/baz',
        ]);

        self::assertTrue($selections->has(ExtensionName::normaliseFromString('foo')));
        self::assertFalse($selections->has(ExtensionName::normaliseFromString('baz')));
        self::assertSame('bat/baz', $selections->packageFor(ExtensionName::normaliseFromString('bar')));
    }
}
