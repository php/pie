<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Php\Pie\Platform\PrePackagedSourceAssetName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrePackagedSourceAssetName::class)]
final class PrePackagedSourceAssetNameTest extends TestCase
{
    public function testPackageNames(): void
    {
        self::markTestIncomplete('todo'); // @todo
    }
}
