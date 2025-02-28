<?php

declare(strict_types=1);

namespace Php\PieUnitTest\File;

use Php\Pie\File\Sudo;
use Php\Pie\File\SudoUnlink;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function chmod;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(SudoUnlink::class)]
final class SudoUnlinkTest extends TestCase
{
    public function testSingleFile(): void
    {
        if (! Sudo::exists()) {
            self::markTestSkipped('Cannot test sudo file_put_contents without sudo');
        }

        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_sudo_file_put_contents_', true);
        touch($file);
        chmod($file, 0000);
        self::assertFileExists($file);

        SudoUnlink::singleFile($file);

        self::assertFileDoesNotExist($file);
    }
}
