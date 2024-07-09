<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform\TargetPhp;

use Composer\Package\PackageInterface;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPhp\PhpBinaryPathBasedPlatformRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(PhpBinaryPathBasedPlatformRepository::class)]
final class PhpBinaryPathBasedPlatformRepositoryTest extends TestCase
{
    public function testPlatformRepositoryContainsExpectedPacakges(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::once())
            ->method('version')
            ->willReturn('8.1.0');
        $phpBinaryPath->expects(self::once())
            ->method('extensions')
            ->willReturn([
                'json' => '8.1.0-extra',
                'foo' => '8.1.0',
                'without-version' => '0',
                'another' => '1.2.3-alpha.34',
            ]);

        $platformRepository = new PhpBinaryPathBasedPlatformRepository($phpBinaryPath);

        self::assertSame(
            [
                'php:8.1.0',
                'ext-json:8.1.0',
                'ext-foo:8.1.0',
                'ext-without-version:0',
                'ext-another:1.2.3-alpha.34',
            ],
            array_map(
                static fn (PackageInterface $package): string => $package->getName() . ':' . $package->getPrettyVersion(),
                $platformRepository->getPackages(),
            ),
        );
    }
}
