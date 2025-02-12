<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing;

use Php\Pie\Installing\UninstallUsingUnlink;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UninstallUsingUnlink::class)]
final class UninstallUsingUnlinkTest extends TestCase
{
    public function testMissingMetadataThrowsException(): void
    {
        self::fail('to be implemented'); // @todo
    }

    public function testBinaryFileIsRemoved(): void
    {
        self::fail('to be implemented'); // @todo
    }
}
