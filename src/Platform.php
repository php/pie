<?php

declare(strict_types=1);

namespace Php\Pie;

use Composer\Util\Platform as ComposerPlatform;
use Composer\Util\Silencer;
use Php\Pie\Platform\TargetPlatform;
use RuntimeException;

use function array_keys;
use function implode;
use function md5;
use function rtrim;
use function strpos;
use function strtr;

use const DIRECTORY_SEPARATOR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class Platform
{
    private static function useXdg(): bool
    {
        foreach (array_keys($_SERVER) as $key) {
            /** @psalm-suppress RedundantCastGivenDocblockType */
            if (strpos((string) $key, 'XDG_') === 0) {
                return true;
            }
        }

        return (bool) Silencer::call('is_dir', '/etc/xdg');
    }

    /** @throws RuntimeException */
    private static function getUserDir(): string
    {
        $home = ComposerPlatform::getEnv('HOME');
        if ($home === false || $home === '') {
            throw new RuntimeException('The HOME or PIE_WORKING_DIRECTORY environment variable must be set for PIE to run correctly');
        }

        return rtrim(strtr($home, '\\', '/'), '/');
    }

    /**
     * This is essentially a Composer-controlled `vendor` directory that has downloaded sources
     *
     * @throws RuntimeException
     */
    public static function getPieWorkingDirectory(TargetPlatform $targetPlatform): string
    {
        // Simple hash of the target platform so we can build against different PHP installs on the same system
        $targetPlatformPath = DIRECTORY_SEPARATOR . 'php' . $targetPlatform->phpBinaryPath->majorMinorVersion() . '_' . md5(implode(
            '|',
            [
                $targetPlatform->operatingSystem->name,
                $targetPlatform->phpBinaryPath->phpBinaryPath,
                $targetPlatform->phpBinaryPath->version(),
                $targetPlatform->architecture->name,
                $targetPlatform->threadSafety->name,
                $targetPlatform->windowsCompiler?->name ?? 'x',
            ],
        ));

        $home = ComposerPlatform::getEnv('PIE_WORKING_DIRECTORY');
        if ($home !== false && $home !== '') {
            return $home . $targetPlatformPath;
        }

        if (ComposerPlatform::isWindows()) {
            $appData = ComposerPlatform::getEnv('APPDATA');
            if ($appData === false || $appData === '') {
                throw new RuntimeException('The APPDATA or PIE_WORKING_DIRECTORY environment variable must be set for PIE to run correctly');
            }

            return rtrim(strtr($appData, '\\', '/'), '/') . '/PIE' . $targetPlatformPath . '/';
        }

        $userDir = self::getUserDir();
        $dirs    = [];

        if (self::useXdg()) {
            // XDG Base Directory Specifications
            $xdgConfig = ComposerPlatform::getEnv('XDG_CONFIG_HOME');
            if ($xdgConfig === false || $xdgConfig === '') {
                $xdgConfig = $userDir . '/.config';
            }

            $dirs[] = $xdgConfig . '/pie';
        }

        $dirs[] = $userDir . '/.pie';

        // select first dir which exists of: $XDG_CONFIG_HOME/pie or ~/.pie
        foreach ($dirs as $dir) {
            if (Silencer::call('is_dir', $dir)) {
                return $dir . $targetPlatformPath;
            }
        }

        // if none exists, we default to first defined one (XDG one if system uses it, or ~/.pie otherwise)
        return $dirs[0] . $targetPlatformPath;
    }

    /** @return non-empty-string */
    public static function getPieJsonFilename(TargetPlatform $targetPlatform): string
    {
        return self::getPieWorkingDirectory($targetPlatform) . '/pie.json';
    }
}
