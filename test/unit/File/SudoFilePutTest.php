<?php

declare(strict_types=1);

namespace Php\PieUnitTest\File;

use Composer\Util\Filesystem;
use Php\Pie\File\Sudo;
use Php\Pie\File\SudoFilePut;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_get_contents;
use function mkdir;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(SudoFilePut::class)]
final class SudoFilePutTest extends TestCase
{
    public function testSudoFilePutContentsWithExistingWritableFile(): void
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_sudo_file_put_contents_', true);
        touch($file);

        SudoFilePut::contents($file, 'the content');

        self::assertSame('the content', file_get_contents($file));

        (new Filesystem())->remove($file);
    }

    public function testSudoFilePutContentsWithNewFileInWritablePath(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_sudo_file_put_contents_', true);
        mkdir($path);
        $file = $path . DIRECTORY_SEPARATOR . 'testfile';

        SudoFilePut::contents($file, 'the content');

        self::assertSame('the content', file_get_contents($file));

        (new Filesystem())->remove($path);
    }

    public function testSudoFilePutContentsWithExistingUnwritableFile(): void
    {
        if (! Sudo::exists()) {
            self::markTestSkipped('Cannot test sudo file_put_contents without sudo');
        }

        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_sudo_file_put_contents_', true);
        touch($file);
        chmod($file, 0000);

        SudoFilePut::contents($file, 'the content');

        chmod($file, 777);
        self::assertSame('the content', file_get_contents($file));

        (new Filesystem())->remove($file);
    }

    public function testSudoFilePutContentsWithNewFileInUnwritablePath(): void
    {
        if (! Sudo::exists()) {
            self::markTestSkipped('Cannot test sudo file_put_contents without sudo');
        }

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_sudo_file_put_contents_', true);
        mkdir($path);
        chmod($path, 0444);
        $file = $path . DIRECTORY_SEPARATOR . 'testfile';

        SudoFilePut::contents($file, 'the content');

        chmod($path, 0777);
        self::assertSame('the content', file_get_contents($file));

        (new Filesystem())->remove($path);
    }
}
