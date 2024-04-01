<?php

declare(strict_types=1);

namespace Php\PieUnitTest\TargetPhp;

use Php\Pie\TargetPhp\PhpBinaryPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;
use const PHP_RELEASE_VERSION;

#[CoversClass(PhpBinaryPath::class)]
final class PhpBinaryPathTest extends TestCase
{
    public function testVersionFromCurrentProcess(): void
    {
        self::assertSame(
            sprintf('%s.%s.%s', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION),
            PhpBinaryPath::fromCurrentProcess()->version(),
        );
    }
}
