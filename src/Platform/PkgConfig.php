<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Php\Pie\Util\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function array_combine;
use function array_intersect;
use function array_values;
use function count;
use function explode;
use function trim;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
class PkgConfig
{
    /** @param list<string> $allLibraries */
    private function __construct(private readonly array $allLibraries)
    {
    }

    public static function detect(): self
    {
        try {
            $listAll = Process::run(['pkg-config', '--list-all']);
        } catch (ProcessFailedException) {
            return new self([]);
        }

        $allLibraries = [];
        foreach (explode("\n", $listAll) as $line) {
            $library = explode(' ', trim($line), 2)[0];
            if ($library === '') {
                continue;
            }

            $allLibraries[] = $library;
        }

        return new self($allLibraries);
    }

    /**
     * @param list<string> $libraries
     *
     * @return array<string, string> map of library name to version, only for the libraries that are available
     */
    public function versionsOf(array $libraries): array
    {
        $availableLibraries = array_values(array_intersect($libraries, $this->allLibraries));

        if ($availableLibraries === []) {
            return [];
        }

        try {
            $versions = explode("\n", Process::run(['pkg-config', '--modversion', ...$availableLibraries]));
        } catch (ProcessFailedException) {
            return $this->versionsOfEachIndividually($availableLibraries);
        }

        if (count($versions) !== count($availableLibraries)) {
            return $this->versionsOfEachIndividually($availableLibraries);
        }

        return array_combine($availableLibraries, $versions);
    }

    /**
     * @param list<string> $libraries
     *
     * @return array<string, string>
     */
    private function versionsOfEachIndividually(array $libraries): array
    {
        $versions = [];
        foreach ($libraries as $library) {
            try {
                $versions[$library] = Process::run(['pkg-config', '--modversion', $library]);
            } catch (ProcessFailedException) {
                continue;
            }
        }

        return $versions;
    }
}
