<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Composer\Package\CompletePackageInterface;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\PrePackagedSourceAssetName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrePackagedSourceAssetName::class)]
final class PrePackagedSourceAssetNameTest extends TestCase
{
    public function testPackageNames(): void
    {
        self::assertSame(
            [
                'php_foobar-1.2.3-src.tgz',
                'php_foobar-1.2.3-src.zip',
            ],
            PrePackagedSourceAssetName::packageNames(
                new Package(
                    $this->createMock(CompletePackageInterface::class),
                    ExtensionType::PhpModule,
                    ExtensionName::normaliseFromString('foobar'),
                    'foo/bar',
                    '1.2.3',
                    null,
                ),
            ),
        );
    }
}
