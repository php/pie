<?php

declare(strict_types=1);

namespace Php\Pie\Util;

use Composer\InstalledVersions;

use function class_exists;

final class PieVersion
{
    public static function get(): string
    {
        $pieVersion = '@pie_version@';

        /**
         * @psalm-suppress RedundantCondition
         * @noinspection PhpConditionAlreadyCheckedInspection
         */
        if ($pieVersion === '@pie_version' . '@') {
            if (! class_exists(InstalledVersions::class)) {
                /**
                 * Note: magic constant that causes Symfony Console to not display a version
                 * {@see Application::getLongVersion()}
                 */
                return 'UNKNOWN';
            }

            $installedVersion = InstalledVersions::getVersion(InstalledVersions::getRootPackage()['name']);
            if ($installedVersion === null) {
                return 'UNKNOWN';
            }

            return $installedVersion;
        }

        /** @psalm-suppress NoValue */
        return $pieVersion;
    }
}
