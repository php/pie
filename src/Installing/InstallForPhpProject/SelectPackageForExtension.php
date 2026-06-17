<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\Composer;
use Composer\IO\IOInterface;
use OutOfRangeException;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\ExtensionName;

use function array_key_exists;
use function array_map;
use function array_merge;
use function assert;
use function count;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class SelectPackageForExtension
{
    public function __construct(
        private readonly FindMatchingPackages $findMatchingPackages,
        private readonly IOInterface $io,
    ) {
    }

    /**
     * Determine which package to install where an extension is missing.
     *
     * 1) if a selection is made (`--select...`), the selected {@see RequestedPackageAndVersion} is returned
     * 2) the Packagist provider endpoint is called go list potential package options
     * 3) in interactive mode, the options are presented interactively; if no selection is made, `null` is returned,
     *      otherwise the selected {@see RequestedPackageAndVersion} is returned.
     *    in non-interactive mode (e.g. CI/Docker builds etc), we do NOT install anything, we just tell the user to
     *      provide the appropriate `--select` flags
     *
     * @param array<non-empty-string, non-empty-string> $extensionToPackageSelections
     *
     * @throws NoMatchingPackagesFound
     * @throws PackageSelectionRequired
     */
    public function __invoke(
        ExtensionName $extension,
        string $linkRequiresConstraint,
        array $extensionToPackageSelections,
        Composer $pieComposer,
        bool $isInteractive,
    ): RequestedPackageAndVersion|null {
        if (array_key_exists($extension->name(), $extensionToPackageSelections)) {
            return new RequestedPackageAndVersion(
                $extensionToPackageSelections[$extension->name()],
                $this->normaliseConstraint($linkRequiresConstraint),
            );
        }

        try {
            $matches = $this->findMatchingPackages->byProvider($pieComposer, $extension);
        } catch (OutOfRangeException) {
            $matches = [];
        }

        if (! count($matches)) {
            throw NoMatchingPackagesFound::forExtension($extension);
        }

        if (! $isInteractive) {
            throw PackageSelectionRequired::forExtensionWithMatches($extension, $matches);
        }

        $selectedPackageAnswer = (int) $this->io->select(
            "\nThe following packages may be suitable, which would you like to install: ",
            array_merge(
                ['None'],
                array_map(
                    static fn (array $match): string => sprintf('%s: %s', $match['name'], $match['description'] ?? 'no description available'),
                    $matches,
                ),
            ),
            '0',
        );

        if ($selectedPackageAnswer === 0) {
            $this->io->write('Okay I won\'t install anything for ' . $extension->name());

            return null;
        }

        $matchesKey = $selectedPackageAnswer - 1;
        assert(array_key_exists($matchesKey, $matches));
        assert($matches[$matchesKey]['name'] !== '');

        return new RequestedPackageAndVersion(
            $matches[$matchesKey]['name'],
            $this->normaliseConstraint($linkRequiresConstraint),
        );
    }

    /** @return non-empty-string|null */
    private function normaliseConstraint(string $linkRequiresConstraint): string|null
    {
        if ($linkRequiresConstraint === '*' || $linkRequiresConstraint === '') {
            return null;
        }

        return $linkRequiresConstraint;
    }
}
