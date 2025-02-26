<?php

declare(strict_types=1);

namespace Php\Pie\File;

use Symfony\Component\Process\ExecutableFinder;
use Throwable;

use function is_string;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class Sudo
{
    /** @var non-empty-string|null */
    private static string|null $memoizedSudo = null;

    /**
     * @return non-empty-string
     *
     * @throws SudoNotFoundOnSystem
     */
    public static function find(): string
    {
        if (! is_string(self::$memoizedSudo)) {
            $sudo = (new ExecutableFinder())->find('sudo');

            if ($sudo === null || $sudo === '') {
                throw SudoNotFoundOnSystem::new();
            }

            self::$memoizedSudo = $sudo;
        }

        return self::$memoizedSudo;
    }

    public static function exists(): bool
    {
        try {
            self::find();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
