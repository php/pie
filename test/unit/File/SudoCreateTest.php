<?php

declare(strict_types=1);

namespace Php\PieUnitTest\File;

use Composer\Util\Filesystem;
use Php\Pie\File\Sudo;
use Php\Pie\File\SudoCreate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function chmod;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(SudoCreate::class)]
final class SudoCreateTest extends TestCase
{
    public function testSingleFileCreate(): void
    {
        if (! Sudo::exists()) {
            self::markTestSkipped('Cannot test sudo file_put_contents without sudo');
        }

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_sudo_create_', true);
        mkdir($path, 0444);
        $file = $path . DIRECTORY_SEPARATOR . uniqid('pie_test_file_', true);
        self::assertFileDoesNotExist($file);

        SudoCreate::file($file);

        chmod($path, 0777);
        chmod($file, 0777);
        self::assertFileExists($file);

        (new Filesystem())->remove($path);
    }
}
