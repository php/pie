<?php

declare(strict_types=1);

namespace Php\PieUnitTest\SelfManage\Update;

use Composer\Util\Filesystem;
use Php\Pie\SelfManage\Update\IsBrewInstallation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;

use function Safe\mkdir;
use function Safe\realpath;
use function Safe\symlink;
use function Safe\touch;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(IsBrewInstallation::class)]
final class IsBrewInstallationTest extends TestCase
{
    /** @return array<non-empty-string, array{0: string, 1: string, 2: bool}> */
    public static function pathProvider(): array
    {
        return [
            'regular-path'                => ['/home/user/.local/bin/pie.phar', '/home/user/.local/bin/pie.phar', false],
            'both-regular-usr-local'      => ['/usr/local/bin/pie', '/usr/local/bin/pie', false],
            'intel-mac-cellar-direct'     => ['/usr/local/Cellar/pie/1.0/bin/pie', '/usr/local/Cellar/pie/1.0/bin/pie', true],
            'apple-silicon-cellar-direct' => ['/opt/homebrew/Cellar/pie/1.0/bin/pie', '/opt/homebrew/Cellar/pie/1.0/bin/pie', true],
            'resolved-is-cellar'          => ['/opt/homebrew/Cellar/pie/1.0/bin/pie', '/usr/local/bin/pie', true],
            'original-is-cellar'          => ['/usr/local/bin/pie', '/opt/homebrew/Cellar/pie/1.0/bin/pie', true],
        ];
    }

    #[DataProvider('pathProvider')]
    public function testIsBrewInstallationWithPaths(
        string $resolvedPath,
        string $originalPath,
        bool $expected,
    ): void {
        self::assertSame($expected, (new IsBrewInstallation())($resolvedPath, $originalPath));
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testSymlinkAtRegularPathPointingIntoBrewCellarIsDetected(): void
    {
        $tmpDir      = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_brew_test_', true);
        $cellarFile  = $tmpDir . '/opt/homebrew/Cellar/pie/1.0/pie.phar';
        $symlinkPath = $tmpDir . '/pie';

        mkdir($tmpDir . '/opt/homebrew/Cellar/pie/1.0', 0777, true);
        touch($cellarFile);
        symlink($cellarFile, $symlinkPath);

        try {
            $resolvedPath = realpath($symlinkPath);
            self::assertTrue((new IsBrewInstallation())($resolvedPath, $symlinkPath));
        } finally {
            (new Filesystem())->remove($tmpDir);
        }
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testSymlinkAtRegularPathPointingToNonBrewPathIsNotDetected(): void
    {
        $tmpDir      = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_brew_test_', true);
        $regularFile = $tmpDir . '/regular/pie.phar';
        $symlinkPath = $tmpDir . '/pie';

        mkdir($tmpDir . '/regular', 0777, true);
        touch($regularFile);
        symlink($regularFile, $symlinkPath);

        try {
            $resolvedPath = realpath($symlinkPath);
            self::assertFalse((new IsBrewInstallation())($resolvedPath, $symlinkPath));
        } finally {
            (new Filesystem())->remove($tmpDir);
        }
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testSymlinkInCellarPointingToRegularPathIsDetectedViaOriginalPath(): void
    {
        $tmpDir      = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_brew_test_', true);
        $regularFile = $tmpDir . '/regular/pie.phar';
        $symlinkPath = $tmpDir . '/opt/homebrew/Cellar/pie/1.0/pie';

        mkdir($tmpDir . '/regular', 0777, true);
        mkdir($tmpDir . '/opt/homebrew/Cellar/pie/1.0', 0777, true);
        touch($regularFile);
        symlink($regularFile, $symlinkPath);

        try {
            $resolvedPath = realpath($symlinkPath);
            self::assertTrue((new IsBrewInstallation())($resolvedPath, $symlinkPath));
        } finally {
            (new Filesystem())->remove($tmpDir);
        }
    }
}
