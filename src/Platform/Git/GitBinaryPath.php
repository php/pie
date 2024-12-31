<?php

declare(strict_types=1);

namespace Php\Pie\Platform\Git;

use Composer\Util\Platform;
use Php\Pie\Util\Process;
use RuntimeException;

use function array_filter;
use function array_map;
use function array_merge;
use function explode;
use function file_exists;
use function file_get_contents;
use function is_executable;
use function preg_match;
use function rtrim;
use function trim;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
class GitBinaryPath
{
    private function __construct(
        public readonly string $gitBinaryPath,
    ) {
    }

    /** @param non-empty-string $gitBinary */
    public static function fromGitBinaryPath(string $gitBinary): self
    {
        self::assertValidLookingGitBinary($gitBinary);

        return new self($gitBinary);
    }

    /** @return array<string> The list of cloned submodules */
    public function fetchSubmodules(string $path): array
    {
        $modulesPath = rtrim($path, '/') . '/.gitmodules';

        if (! file_exists($modulesPath)) {
            throw new RuntimeException('No .gitmodules file found in the specified path.');
        }

        $content = file_get_contents($modulesPath);
        if ($content === false) {
            throw new RuntimeException('Unable to read .gitmodules file.');
        }

        $modules = $this->parseGitModules($content);

        if (! $modules) {
            return [];
        }

        return $this->processModules($modules, $path);
    }

    /**
     * @param string $content Raw content of .gitmodules file
     *
     * @return array<Submodule> List of parsed modules
     */
    private function parseGitModules(string $content): array
    {
        $lines       = array_filter(array_map('trim', explode("\n", $content)));
        $modules     = [];
        $currentName = null;
        $currentPath = '';
        $currentUrl  = '';

        $modulePattern = '/^\[submodule "(?P<name>[^"]+)"]$/';
        $configPattern = '/^(?P<key>path|url)\s*=\s*(?P<value>.+)$/';

        foreach ($lines as $line) {
            // do we enter a new module?
            if (preg_match($modulePattern, $line, $matches)) {
                if ($currentName !== null) {
                    $modules[] = new Submodule($currentPath, $currentUrl);
                }

                $currentName = $matches['name'];
                $currentPath = '';
                $currentUrl  = '';

                continue;
            }

            if ($currentName === null || ! preg_match($configPattern, $line, $matches)) {
                continue;
            }

            if ($matches['key'] === 'path') {
                $currentPath = trim($matches['value']);
            } elseif ($matches['key'] === 'url') {
                $currentUrl = trim($matches['value']);
            }
        }

        if ($currentName !== null) {
            $modules[] = new Submodule($currentPath, $currentUrl);
        }

        return $modules;
    }

    /**
     * Process the parsed modules by cloning them and handling recursive submodules.
     *
     * @param non-empty-array<Submodule> $modules List of parsed modules
     *
     * @return array<array-key, string> The list of cloned submodules
     */
    private function processModules(array $modules, string $basePath): array
    {
        $clonedModules = [];

        foreach ($modules as $module) {
            if (! $module->path || ! $module->url) {
                // incomplete module, skip
                continue;
            }

            $targetPath = rtrim($basePath, '/') . '/' . $module->path;

            Process::run([$this->gitBinaryPath, 'clone', $module->url, $targetPath], $basePath, timeout: null);
            $clonedModules[] = $module->url;

            if (! file_exists($targetPath . '/.gitmodules')) {
                continue;
            }

            $clonedModules = array_merge($clonedModules, $this->fetchSubmodules($targetPath));
        }

        return $clonedModules;
    }

    private static function assertValidLookingGitBinary(string $gitBinary): void
    {
        if (! file_exists($gitBinary)) {
            throw Exception\InvalidGitBinaryPath::fromNonExistentgitBinary($gitBinary);
        }

        if (! Platform::isWindows() && ! is_executable($gitBinary)) {
            throw Exception\InvalidGitBinaryPath::fromNonExecutableGitBinary($gitBinary);
        }

        $output = Process::run([$gitBinary, '--version']);

        if (! preg_match('/git version \d+\.\d+\.\d+/', $output)) {
            throw Exception\InvalidGitBinaryPath::fromInvalidGitBinary($gitBinary);
        }
    }
}
