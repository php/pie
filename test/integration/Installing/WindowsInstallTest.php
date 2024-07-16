<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Installing;

use Php\Pie\Installing\WindowsInstall;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WindowsInstall::class)]
final class WindowsInstallTest extends TestCase
{
    private const TEST_EXTENSION_PATH = __DIR__ . '/../../assets/pie_test_ext';

    public function testWindowsInstallCanInstallExtension(): void
    {
        self::markTestIncomplete(__METHOD__ . ' - to be implemented');
    }
}
