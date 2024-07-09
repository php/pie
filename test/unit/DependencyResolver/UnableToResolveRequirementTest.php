<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Composer\Package\PackageInterface;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnableToResolveRequirement::class)]
final class UnableToResolveRequirementTest extends TestCase
{
    public function testToPhpOrZendExtensionWithVersion(): void
    {
        $package = $this->createMock(PackageInterface::class);
        $package->method('getName')->willReturn('baz/bat');

        $exception = UnableToResolveRequirement::toPhpOrZendExtension($package, 'foo/bar', '^1.2');

        self::assertSame('Package baz/bat was not of type php-ext or php-ext-zend (requested foo/bar for version ^1.2).', $exception->getMessage());
    }

    public function testToPhpOrZendExtensionWithoutVersion(): void
    {
        $package = $this->createMock(PackageInterface::class);
        $package->method('getName')->willReturn('baz/bat');

        $exception = UnableToResolveRequirement::toPhpOrZendExtension($package, 'foo/bar', null);

        self::assertSame('Package baz/bat was not of type php-ext or php-ext-zend (requested foo/bar).', $exception->getMessage());
    }

    public function testFromRequirementWithVersion(): void
    {
        $exception = UnableToResolveRequirement::fromRequirement('foo/bar', '^1.2');

        self::assertSame('Unable to find an installable package foo/bar for version ^1.2.', $exception->getMessage());
    }

    public function testFromRequirementWithoutVersion(): void
    {
        $exception = UnableToResolveRequirement::fromRequirement('foo/bar', null);

        self::assertSame('Unable to find an installable package foo/bar.', $exception->getMessage());
    }
}
