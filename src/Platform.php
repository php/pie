<?php

declare(strict_types=1);

namespace Php\Pie;

use Composer\Util\Platform as ComposerPlatform;
use Composer\Util\Silencer;
use Php\Pie\Platform\TargetPlatform;
use RuntimeException;

use function array_keys;
use function defined;
use function fopen;
use function implode;
use function md5;
use function rtrim;
use function strpos;
use function strtr;

use const DIRECTORY_SEPARATOR;
use const STDIN;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class Platform
{
    public static function isInteractive(): bool
    {
        $stdin = defined('STDIN') ? STDIN : fopen('php://stdin', 'r');

        return ComposerPlatform::getEnv('COMPOSER_NO_INTERACTION') !== '1'
            && $stdin !== false
            && ComposerPlatform::isTty($stdin);
    }

    private static function useXdg(): bool
    {
        foreach (array_keys($_SERVER) as $key) {
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

    public static function getPieBaseWorkingDirectory(): string
    {
        $home = ComposerPlatform::getEnv('PIE_WORKING_DIRECTORY');
        if ($home !== false && $home !== '') {
            return $home;
        }

        if (ComposerPlatform::isWindows()) {
            $appData = ComposerPlatform::getEnv('APPDATA');
            if ($appData === false || $appData === '') {
                throw new RuntimeException('The APPDATA or PIE_WORKING_DIRECTORY environment variable must be set for PIE to run correctly');
            }

            return rtrim(strtr($appData, '\\', '/'), '/') . '/PIE';
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
                return $dir;
            }
        }

        // if none exists, we default to first defined one (XDG one if system uses it, or ~/.pie otherwise)
        return $dirs[0];
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

        return self::getPieBaseWorkingDirectory() . $targetPlatformPath;
    }

    /** @return non-empty-string */
    public static function getPieJsonFilename(TargetPlatform $targetPlatform): string
    {
        return self::getPieWorkingDirectory($targetPlatform) . '/pie.json';
    }
}
