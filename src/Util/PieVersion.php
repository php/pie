<?php

declare(strict_types=1);

namespace Php\Pie\Util;

use Composer\InstalledVersions;

use function class_exists;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class PieVersion
{
    /**
     * Note: magic constant that causes Symfony Console to not display a version
     * {@see Application::getLongVersion()}
     */
    private const SYMFONY_MAGIC_CONST_UNKNOWN = 'UNKNOWN';

    /**
     * This value is replaced dynamically by Box with the real version when
     * we build the PHAR. It is based on the Git tag and/or version
     *
     * It will be replaced with `2.0.0` on an exact tag match, or something
     * like `2.0.0@e558e33` on a commit following a tag.
     *
     * When running not in a PHAR, this will not be replaced, so this
     * method needs additional logic to determine the version.
     *
     * @link https://box-project.github.io/box/configuration/#pretty-git-tag-placeholder-git
     */
    private const PIE_VERSION = '@pie_version@';

    public static function isPharBuild(): bool
    {
        // phpcs:ignore Generic.Strings.UnnecessaryStringConcat.Found
        return self::PIE_VERSION !== '@pie_version' . '@';
    }

    /**
     * A static method to try to find the version of PIE you are currently
     * running. If running in the PHAR built with Box, this should return a
     * realistic-looking version; usually either a tag (e.g. `2.0.0`), or a tag
     * and following commit short hash (e.g. `2.0.0@e558e33`). If not this will
     * fall back to some other techniques to try to determine a version.
     *
     * @return non-empty-string
     */
    public static function get(): string
    {
        if (self::isPharBuild()) {
            return self::PIE_VERSION;
        }

        if (! class_exists(InstalledVersions::class)) {
            return self::SYMFONY_MAGIC_CONST_UNKNOWN;
        }

        /**
         * This tries to determine the version based on Composer; if we are
         * the root package (i.e. you're developing on it), this will most
         * likely be something like `dev-main` (branch name).
         */
        $installedVersion = InstalledVersions::getVersion(InstalledVersions::getRootPackage()['name']);
        if ($installedVersion === null || $installedVersion === '') {
            return self::SYMFONY_MAGIC_CONST_UNKNOWN;
        }

        return $installedVersion;
    }
}
