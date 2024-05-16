<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Php\Pie\Downloading\Path;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Path::class)]
final class PathTest extends TestCase
{
    public function testVaguelyRandomTempPath(): void
    {
        $path1 = Path::vaguelyRandomTempPath();
        $path2 = Path::vaguelyRandomTempPath();

        self::assertNotSame($path1, $path2);
    }
}
