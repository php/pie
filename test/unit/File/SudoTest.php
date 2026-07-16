<?php

declare(strict_types=1);

namespace Php\PieUnitTest\File;

use Composer\Util\Platform as ComposerPlatform;
use Php\Pie\File\Sudo;
use Php\Pie\File\SudoNotFoundOnSystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sudo::class)]
final class SudoTest extends TestCase
{
    public function testSudoIsNeverDetectedOnWindows(): void
    {
        if (! ComposerPlatform::isWindows()) {
            self::markTestSkipped('This test only applies to Windows, where a native `sudo.exe` may exist but is not usable by PIE');
        }

        self::assertFalse(Sudo::exists());

        $this->expectException(SudoNotFoundOnSystem::class);
        Sudo::find();
    }
}
