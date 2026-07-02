<?php

declare(strict_types=1);

namespace Php\Pie\SelfManage\BuildTools;

use Php\Pie\Platform\PackageManager;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Process\ExecutableFinder;

use function array_key_exists;
use function implode;
use function is_array;
use function Safe\preg_replace;
use function str_replace;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class BinaryBuildToolFinder
{
    /**
     * @param non-empty-string|array<non-empty-string>        $tool
     * @param array<PackageManager::*, non-empty-string|null> $packageManagerPackages
     */
    public function __construct(
        protected readonly string|array $tool,
        private readonly array $packageManagerPackages,
    ) {
    }

    public function toolNames(): string
    {
        return is_array($this->tool) ? implode('/', $this->tool) : $this->tool;
    }

    public function check(TargetPlatform $targetPlatform): bool
    {
        $tools = is_array($this->tool) ? $this->tool : [$this->tool];

        foreach ($tools as $tool) {
            if ((new ExecutableFinder())->find($tool) !== null) {
                return true;
            }
        }

        return false;
    }

    /** @return non-empty-string|null */
    public function packageNameFor(PackageManager $packageManager, TargetPlatform $targetPlatform): string|null
    {
        if (! array_key_exists($packageManager->value, $this->packageManagerPackages) || $this->packageManagerPackages[$packageManager->value] === null) {
            return null;
        }

        if ($this->packageManagerPackages[$packageManager->value] === '{php-config-path}') {
            return preg_replace('((.*)php)', '$1php-config', $targetPlatform->phpBinaryPath->phpBinaryPath);
        }

        // If we need to customise specific package names depending on OS
        // specific parameters, this is likely the place to do it
        return str_replace(
            '{major}',
            (string) $targetPlatform->phpBinaryPath->majorVersion(),
            str_replace(
                '{minor}',
                (string) $targetPlatform->phpBinaryPath->minorVersion(),
                $this->packageManagerPackages[$packageManager->value],
            ),
        );
    }
}
