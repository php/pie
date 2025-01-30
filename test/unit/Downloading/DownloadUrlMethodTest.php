<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Downloading;

use Php\Pie\Downloading\DownloadUrlMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DownloadUrlMethod::class)]
final class DownloadUrlMethodTest extends TestCase
{
    public function testWindowsPackages(): void
    {
        self::markTestIncomplete('todo'); // @todo
    }

    public function testPrePackagedSourceDownloads(): void
    {
        self::markTestIncomplete('todo'); // @todo
    }

    public function testComposerDefaultDownload(): void
    {
        self::markTestIncomplete('todo'); // @todo
    }
}
