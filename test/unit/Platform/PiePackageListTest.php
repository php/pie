<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Platform;

use Composer\Package\CompletePackage;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\PiePackageList;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;

#[CoversClass(PiePackageList::class)]
final class PiePackageListTest extends TestCase
{
    public function testTargetPhpIsAskedForItsExtensionPathOnceWhenVerifyingSeveralPackages(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::once())
            ->method('extensionPath')
            ->willReturn(sys_get_temp_dir());

        $targetPlatform = new TargetPlatform(
            OperatingSystem::NonWindows,
            OperatingSystemFamily::Linux,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::NonThreadSafe,
            1,
            null,
            null,
        );

        $piePackages = new PiePackageList([
            Package::fromComposerCompletePackage(new CompletePackage('foo/not_installed_one', '1.0.0.0', '1.0.0')),
            Package::fromComposerCompletePackage(new CompletePackage('foo/not_installed_two', '1.0.0.0', '1.0.0')),
            Package::fromComposerCompletePackage(new CompletePackage('foo/not_installed_three', '1.0.0.0', '1.0.0')),
        ]);

        self::assertSame([], $piePackages->onlyVerifiedFor($targetPlatform)->packages());
    }
}
