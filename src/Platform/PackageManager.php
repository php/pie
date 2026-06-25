<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Php\Pie\File\Sudo;
use Php\Pie\Platform;
use Php\Pie\Util\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;

use function array_unshift;
use function implode;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
enum PackageManager: string
{
    case Test = 'test';
    case Apt  = 'apt-get';
    case Apk  = 'apk';
    case Dnf  = 'dnf';
    case Yum  = 'yum';
    case Brew = 'brew';

    public static function detect(): self|null
    {
        $executableFinder = new ExecutableFinder();

        foreach (self::cases() as $packageManager) {
            if ($packageManager === self::Test) {
                continue;
            }

            if ($executableFinder->find($packageManager->value) !== null) {
                return $packageManager;
            }
        }

        return null;
    }

    /**
     * @param list<string> $packages
     *
     * @return list<string>
     */
    public function installCommand(array $packages): array
    {
        return match ($this) {
            self::Test => ['echo', '"fake installing ' . implode(', ', $packages) . '"'],
            self::Apt => ['apt-get', 'install', '-y', '--no-install-recommends', '--no-install-suggests', ...$packages],
            self::Apk => ['apk', 'add', '--no-cache', '--virtual', '.php-pie-deps', ...$packages],
            self::Dnf => ['dnf', 'install', '-y', ...$packages],
            self::Yum => ['yum', 'install', '-y', ...$packages],
            self::Brew => ['brew', 'install', ...$packages],
        };
    }

    /** @param list<string> $packages */
    public function install(array $packages): void
    {
        $cmd = self::installCommand($packages);

        // @todo in -vv mode, would be useful to see output from these commands
        try {
            Process::run($cmd);

            return;
        } catch (ProcessFailedException $e) {
            if (Platform::isInteractive() && Process::processProbablyPermissionDenied($e)) {
                array_unshift($cmd, Sudo::find());

                Process::run($cmd);

                return;
            }

            throw $e;
        }
    }
}
