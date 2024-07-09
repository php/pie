<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ThreadSafetyMode::class)]
final class ThreadSafetyModeTest extends TestCase
{
    public function testAsShort(): void
    {
        self::assertSame('ts', ThreadSafetyMode::ThreadSafe->asShort());
        self::assertSame('nts', ThreadSafetyMode::NonThreadSafe->asShort());
    }
}
