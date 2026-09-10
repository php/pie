<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Util;

use Php\Pie\Util\Realpath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function Safe\mkdir;
use function Safe\symlink;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(Realpath::class)]
final class RealpathTest extends TestCase
{
    /** @return non-empty-string */
    private function realTempDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie-test-realpath-', true);
        mkdir($dir, 0777, true);

        return $dir;
    }

    public function testSamePathIsEqual(): void
    {
        $dir = $this->realTempDir();

        self::assertTrue(Realpath::compare($dir, $dir));
    }

    public function testDifferentPathsAreNotEqual(): void
    {
        self::assertFalse(Realpath::compare($this->realTempDir(), $this->realTempDir()));
    }

    public function testSymlinkedPathIsEqualToItsRealTarget(): void
    {
        $realDir = $this->realTempDir();

        $symlinkPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie-test-realpath-symlink-', true);
        symlink($realDir, $symlinkPath);

        self::assertTrue(Realpath::compare($symlinkPath, $realDir));
    }

    public function testTrailingDirectorySeparatorIsNormalised(): void
    {
        $dir = $this->realTempDir();

        self::assertTrue(Realpath::compare($dir, $dir . DIRECTORY_SEPARATOR));
    }
}
