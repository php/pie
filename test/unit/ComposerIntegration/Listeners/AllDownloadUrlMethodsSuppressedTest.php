<?php

declare(strict_types=1);

namespace Php\PieUnitTest\ComposerIntegration\Listeners;

use Composer\Package\CompletePackageInterface;
use Php\Pie\ComposerIntegration\Listeners\AllDownloadUrlMethodsSuppressed;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllDownloadUrlMethodsSuppressed::class)]
final class AllDownloadUrlMethodsSuppressedTest extends TestCase
{
    public function testForPackage(): void
    {
        self::assertSame(
            'Could not find a way to download foo/bar as all possible download URL methods were suppressed',
            AllDownloadUrlMethodsSuppressed::forPackage(new Package(
                $this->createMock(CompletePackageInterface::class),
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('bar'),
                'foo/bar',
                '1.2.3',
                null,
            ))->getMessage(),
        );
    }
}
