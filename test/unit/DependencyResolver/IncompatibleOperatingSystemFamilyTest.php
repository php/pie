<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Php\Pie\DependencyResolver\IncompatibleOperatingSystemFamily;
use Php\Pie\Platform\OperatingSystemFamily;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IncompatibleOperatingSystemFamily::class)]
class IncompatibleOperatingSystemFamilyTest extends TestCase
{
    public function testCreateNotInCompatibleOperatingSystemFamiliesException(): void
    {
        $exception = IncompatibleOperatingSystemFamily::notInCompatibleOperatingSystemFamilies(
            [OperatingSystemFamily::Windows, OperatingSystemFamily::Linux],
            OperatingSystemFamily::Darwin,
        );

        self::assertSame(
            'This extension does not support the "Darwin" operating system family. It is compatible with the following families: "Windows", "Linux".',
            $exception->getMessage(),
        );
    }

    public function testCreateInIncompatibleOperatingSystemFamilyException(): void
    {
        $exception = IncompatibleOperatingSystemFamily::inIncompatibleOperatingSystemFamily(
            [OperatingSystemFamily::Windows, OperatingSystemFamily::Linux],
            OperatingSystemFamily::Darwin,
        );

        self::assertSame(
            'This extension does not support the "Darwin" operating system family. It is incompatible with the following families: "Windows", "Linux".',
            $exception->getMessage(),
        );
    }
}
