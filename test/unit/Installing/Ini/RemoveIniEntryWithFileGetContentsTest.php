<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\Ini;

use Php\Pie\Installing\Ini\RemoveIniEntryWithFileGetContents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoveIniEntryWithFileGetContents::class)]
final class RemoveIniEntryWithFileGetContentsTest extends TestCase
{
    public function testRelevantIniFilesHaveExtensionRemoved(): void
    {
        self::fail('to be implemented'); // @todo
    }
}
