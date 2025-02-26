<?php

declare(strict_types=1);

namespace Php\PieUnitTest\File;

use Php\Pie\File\Sudo;
use Php\Pie\File\SudoFilePut;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_get_contents;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(SudoFilePut::class)]
final class SudoFilePutTest extends TestCase
{
    public function testSudoFilePutContents(): void
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
    }
}
