<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform\TargetPhp;

use Composer\Package\BasePackage;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPhp\ResolveTargetPhpToPlatformRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_key_first;
use function assert;
use function count;

#[CoversClass(ResolveTargetPhpToPlatformRepository::class)]
final class ResolveTargetPhpToPlatformRepositoryTest extends TestCase
{
    public function testPlatformRepositoryIsReturned(): void
    {
        $php = PhpBinaryPath::fromCurrentProcess();

        $platformRepository = (new ResolveTargetPhpToPlatformRepository())($php);

        $phpPackages = array_filter(
            $platformRepository->getPackages(),
            static function (BasePackage $package): bool {
                return $package->getPrettyName() === 'php';
            },
        );

        self::assertCount(1, $phpPackages);
        assert(count($phpPackages) > 0);

        $phpPackage = $phpPackages[array_key_first($phpPackages)];

        self::assertSame($php->version(), $phpPackage->getPrettyVersion());
    }
}
