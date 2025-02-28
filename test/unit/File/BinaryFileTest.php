<?php

declare(strict_types=1);

namespace Php\PieUnitTest\File;

use Php\Pie\File\BinaryFile;
use Php\Pie\File\BinaryFileFailedVerification;
use Php\Pie\Util\FileNotFound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BinaryFile::class)]
final class BinaryFileTest extends TestCase
{
    private const TEST_FILE      = __DIR__ . '/../../assets/test-zip.zip';
    private const TEST_FILE_HASH = '64e40b4a66831437a3cc6b899ea71a36765ccb435f8831ab20d49f8ce3f806fa';

    public function testVerifySucceedsWithGoodHash(): void
    {
        $expectation = new BinaryFile(
            self::TEST_FILE,
            self::TEST_FILE_HASH,
        );

        $this->expectNotToPerformAssertions();
        $expectation->verify();
    }

    public function testVerifyFailsWithFileThatDoesNotExist(): void
    {
        $expectation = new BinaryFile(
            '/path/to/a/file/that/does/not/exist',
            self::TEST_FILE_HASH,
        );

        $this->expectException(FileNotFound::class);
        $expectation->verify();
    }

    public function testVerifyFailsWithWrongHash(): void
    {
        $expectation = new BinaryFile(
            self::TEST_FILE,
            'another hash that is wrong',
        );

        $this->expectException(BinaryFileFailedVerification::class);
        $this->expectExceptionMessageMatches('/File "[^"]+" failed checksum verification\. Expected [^\.]+\.\.\., was [^\.]+\.\.\./');
        $expectation->verify();
    }

    public function testVerifyFailsWithDifferentFile(): void
    {
        $expectation = new BinaryFile(
            self::TEST_FILE,
            self::TEST_FILE_HASH,
        );

        $this->expectException(BinaryFileFailedVerification::class);
        $this->expectExceptionMessageMatches('/Expected file "[^"]+" but actual file was "[^"]+"/');
        $expectation->verifyAgainstOther(new BinaryFile(
            __FILE__,
            self::TEST_FILE_HASH,
        ));
    }
}
