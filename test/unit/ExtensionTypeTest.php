<?php

declare(strict_types=1);

namespace Php\PieUnitTest;

use Php\Pie\ExtensionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExtensionType::class)]
final class ExtensionTypeTest extends TestCase
{
    public function testIsValid(): void
    {
        self::assertTrue(ExtensionType::isValid('php-ext'));
        self::assertTrue(ExtensionType::isValid('php-ext-zend'));
        self::assertFalse(ExtensionType::isValid('project'));
        self::assertFalse(ExtensionType::isValid('library'));
        self::assertFalse(ExtensionType::isValid('metapackage'));
        self::assertFalse(ExtensionType::isValid('composer-plugin'));
    }
}
