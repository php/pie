<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\Update;

use Php\Pie\Platform;
use Php\Pie\Platform\Architecture;
use RuntimeException;

use function php_uname;
use function sprintf;

use const PHP_OS_FAMILY;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
enum PieAssetName: string
{
    case Phar        = 'pie.phar';
    case PharNightly = 'pie-nightly.phar';
    case LinuxX64    = 'pie-Linux-X64';
    case LinuxArm64  = 'pie-Linux-ARM64';
    case MacOsArm64  = 'pie-macOS-ARM64';
    case WindowsX64  = 'pie-Windows-X64.exe';

    public static function forRunningPie(): self
    {
        if (! Platform::isRunningStaticPhp()) {
            return self::Phar;
        }

        $machineType = php_uname('m');

        return self::forExecutable(PHP_OS_FAMILY, Architecture::parseArchitecture($machineType !== '' ? $machineType : 'unknown'));
    }

    public function nightly(): self
    {
        return match ($this) {
            self::Phar, self::PharNightly => self::PharNightly,
            default => $this,
        };
    }

    private static function forExecutable(string $osFamily, Architecture $architecture): self
    {
        return match ([$osFamily, $architecture]) {
            ['Linux', Architecture::x86_64] => self::LinuxX64,
            ['Linux', Architecture::arm64] => self::LinuxArm64,
            ['Darwin', Architecture::arm64] => self::MacOsArm64,
            ['Windows', Architecture::x86_64] => self::WindowsX64,
            default => throw new RuntimeException(sprintf(
                'There is no PIE executable available for %s %s',
                $osFamily,
                $architecture->name,
            )),
        };
    }
}
