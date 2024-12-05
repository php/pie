<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\BinaryFile;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;
use ReflectionClass;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_values;
use function count;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class PickBestSetupIniApproach implements SetupIniApproach
{
    /** @var list<SetupIniApproach>|null */
    private array|null $memoizedApproachesThatCanBeUsed = null;

    /** @param list<SetupIniApproach> $possibleApproaches */
    public function __construct(
        private readonly array $possibleApproaches,
    ) {
    }

    /** @return list<SetupIniApproach> */
    private function approachesThatCanBeUsed(TargetPlatform $targetPlatform): array
    {
        if ($this->memoizedApproachesThatCanBeUsed === null) {
            $this->memoizedApproachesThatCanBeUsed = array_values(array_filter(
                $this->possibleApproaches,
                static fn (SetupIniApproach $approach) => $approach->canBeUsed($targetPlatform),
            ));
        }

        return $this->memoizedApproachesThatCanBeUsed;
    }

    public function canBeUsed(TargetPlatform $targetPlatform): bool
    {
        return count($this->approachesThatCanBeUsed($targetPlatform)) > 0;
    }

    public function setup(
        TargetPlatform $targetPlatform,
        DownloadedPackage $downloadedPackage,
        BinaryFile $binaryFile,
        OutputInterface $output,
    ): bool {
        $approaches = $this->approachesThatCanBeUsed($targetPlatform);

        if (count($approaches) === 0) {
            $output->writeln('No INI setup approaches can be used on this platform.', OutputInterface::VERBOSITY_VERBOSE);

            return false;
        }

        foreach ($approaches as $approach) {
            $output->writeln(
                sprintf(
                    'Trying to enable extension using %s',
                    (new ReflectionClass($approach))->getShortName(),
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );
            if ($approach->setup($targetPlatform, $downloadedPackage, $binaryFile, $output)) {
                return true;
            }
        }

        $output->writeln('None of the INI setup approaches succeeded.', OutputInterface::VERBOSITY_VERBOSE);

        return false;
    }
}
