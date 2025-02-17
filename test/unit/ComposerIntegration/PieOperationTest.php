<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration;

use Php\Pie\ComposerIntegration\PieOperation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PieOperation::class)]
final class PieOperationTest extends TestCase
{
    public function testShouldBuild(): void
    {
        self::assertFalse(PieOperation::Resolve->shouldBuild());
        self::assertFalse(PieOperation::Download->shouldBuild());
        self::assertTrue(PieOperation::Build->shouldBuild());
        self::assertTrue(PieOperation::Install->shouldBuild());
        self::assertFalse(PieOperation::Uninstall->shouldBuild());
    }

    public function testShouldInstall(): void
    {
        self::assertFalse(PieOperation::Resolve->shouldInstall());
        self::assertFalse(PieOperation::Download->shouldInstall());
        self::assertFalse(PieOperation::Build->shouldInstall());
        self::assertTrue(PieOperation::Install->shouldInstall());
        self::assertFalse(PieOperation::Uninstall->shouldBuild());
    }
}
