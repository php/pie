<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Php\Pie\Platform\InstalledPiePackages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstalledPiePackages::class)]
final class InstalledPiePackagesTest extends TestCase
{
    public function testAllPiePackages(): void
    {
        self::fail('to be implemented'); // @todo implement this test
    }
}
