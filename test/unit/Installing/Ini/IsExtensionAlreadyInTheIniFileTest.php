<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\Ini;

use Php\Pie\ExtensionName;
use Php\Pie\Installing\Ini\IsExtensionAlreadyInTheIniFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IsExtensionAlreadyInTheIniFile::class)]
final class IsExtensionAlreadyInTheIniFileTest extends TestCase
{
    private const EXAMPLE_INI_FILE_DIRECTORY = __DIR__ . '/../../../assets/example_ini_files';
    private const EXAMPLE_EXTENSION_NAME     = 'foobar';

    public function testReturnsTrueWhenExtensionIsInTheIniFile(): void
    {
        self::assertTrue((new IsExtensionAlreadyInTheIniFile())(
            self::EXAMPLE_INI_FILE_DIRECTORY . '/with_extension.ini',
            ExtensionName::normaliseFromString(self::EXAMPLE_EXTENSION_NAME),
        ));
    }

    public function testReturnsTrueWhenZendExtensionIsInTheIniFile(): void
    {
        self::assertTrue((new IsExtensionAlreadyInTheIniFile())(
            self::EXAMPLE_INI_FILE_DIRECTORY . '/with_zend_extension.ini',
            ExtensionName::normaliseFromString(self::EXAMPLE_EXTENSION_NAME),
        ));
    }

    public function testReturnsFalseWhenExtensionIsNotInTheIniFile(): void
    {
        self::assertFalse((new IsExtensionAlreadyInTheIniFile())(
            self::EXAMPLE_INI_FILE_DIRECTORY . '/without_extension.ini',
            ExtensionName::normaliseFromString(self::EXAMPLE_EXTENSION_NAME),
        ));
    }

    public function testReturnsFalseWhenExtensionIsInTheIniFileButCommented(): void
    {
        self::assertFalse((new IsExtensionAlreadyInTheIniFile())(
            self::EXAMPLE_INI_FILE_DIRECTORY . '/with_commented_extension.ini',
            ExtensionName::normaliseFromString(self::EXAMPLE_EXTENSION_NAME),
        ));
    }
}
