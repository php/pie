<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Composer\Util\Platform;
use Php\Pie\Platform\MakePath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MakePath::class)]
final class MakePathTest extends TestCase
{
    public function testGuessingFindsMakePath(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('Guessing make path is not done for Windows as we are not building for Windows.');
        }

        self::assertNotEmpty(MakePath::guess());
    }
}
