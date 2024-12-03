<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Php\Pie\Platform\OperatingSystemFamily;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperatingSystemFamily::class)]
class OperatingSystemFamilyTest extends TestCase
{
    public function testAsValuesList(): void
    {
        self::assertSame(
            [
                'windows',
                'bsd',
                'darwin',
                'solaris',
                'linux',
                'unknown',
            ],
            OperatingSystemFamily::asValuesList(),
        );
    }
}
