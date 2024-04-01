<?php

declare(strict_types=1);

namespace Php\PieUnitTest\TargetPhp;

use Php\Pie\TargetPhp\PhpBinaryPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use const PHP_VERSION;

#[CoversClass(PhpBinaryPath::class)]
final class PhpBinaryPathTest extends TestCase
{
    public function testVersionFromCurrentProcess(): void
    {
        self::assertSame(PHP_VERSION, PhpBinaryPath::fromCurrentProcess()->version());
    }
}
