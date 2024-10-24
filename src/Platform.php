<?php

declare(strict_types=1);

namespace Php\Pie;

use Composer\Util\Platform as ComposerPlatform;
use Composer\Util\Silencer;

class Platform
{
    private static function useXdg(): bool
    {
        foreach (array_keys($_SERVER) as $key) {
            if (strpos((string) $key, 'XDG_') === 0) {
                return true;
            }
        }

        if (Silencer::call('is_dir', '/etc/xdg')) {
            return true;
        }

        return false;
    }

    /**
     * @throws \RuntimeException
     */
    private static function getUserDir(): string
    {
        $home = ComposerPlatform::getEnv('HOME');
        if (!$home) {
            throw new \RuntimeException('The HOME or PIE_WORKING_DIRECTORY environment variable must be set for PIE to run correctly');
        }

        return rtrim(strtr($home, '\\', '/'), '/');
    }

    /**
     * This is essentially a Composer-controlled `vendor` directory that has downloaded sources
     *
     * @throws \RuntimeException
     */
    public static function getPieWorkingDirectory(): string
    {
        $home = ComposerPlatform::getEnv('PIE_WORKING_DIRECTORY');
        if ($home) {
            return $home;
        }

        if (ComposerPlatform::isWindows()) {
            if (!ComposerPlatform::getEnv('APPDATA')) {
                throw new \RuntimeException('The APPDATA or PIE_WORKING_DIRECTORY environment variable must be set for PIE to run correctly');
            }

            return rtrim(strtr(ComposerPlatform::getEnv('APPDATA'), '\\', '/'), '/') . '/PIE';
        }

        $userDir = self::getUserDir();
        $dirs = [];

        if (self::useXdg()) {
            // XDG Base Directory Specifications
            $xdgConfig = ComposerPlatform::getEnv('XDG_CONFIG_HOME');
            if (!$xdgConfig) {
                $xdgConfig = $userDir . '/.config';
            }

            $dirs[] = $xdgConfig . '/pie';
        }

        $dirs[] = $userDir . '/.pie';

        // select first dir which exists of: $XDG_CONFIG_HOME/pie or ~/.pie
        foreach ($dirs as $dir) {
            if (Silencer::call('is_dir', $dir)) {
                return $dir;
            }
        }

        // if none exists, we default to first defined one (XDG one if system uses it, or ~/.pie otherwise)
        return $dirs[0];
    }

    public static function getPieJsonFilename()
    {
        return self::getPieWorkingDirectory() . '/pie.json';
    }
}
