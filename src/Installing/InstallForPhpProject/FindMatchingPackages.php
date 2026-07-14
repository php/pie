<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\Composer;
use Composer\Package\CompletePackageInterface;
use Composer\Repository\RepositoryInterface;
use OutOfRangeException;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;

use function array_filter;
use function array_key_exists;
use function array_merge;
use function array_values;
use function count;
use function usort;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @phpstan-type MatchingPackages = list<array{name: string, description: ?string, ...}>
 */
class FindMatchingPackages
{
    /**
     * @param list<array{name: string, description: ?string, downloads?: int}> $matches
     * @param non-empty-string                                                 $searchTerm
     *
     * @return MatchingPackages
     */
    private function filterToCompatible(Composer $pieComposer, array $matches, string $searchTerm): array
    {
        $normalisedExtensionNameIfValid = ExtensionName::isValidExtensionName($searchTerm)
            ? ExtensionName::normaliseFromString($searchTerm)
            : null;

        $matches = array_filter(
            $matches,
            static function (array $match) use ($pieComposer, $normalisedExtensionNameIfValid): bool {
                $package = $pieComposer->getRepositoryManager()->findPackage($match['name'], '*');

                /** Don't include abandoned packages */
                if ($package instanceof CompletePackageInterface && $package->isAbandoned()) {
                    return false;
                }

                /** Allows "search results", used for {@see bySearching()} where the search term might not be an extension name*/
                if ($normalisedExtensionNameIfValid === null) {
                    return true;
                }

                /** Don't include packages without type php-ext or php-ext-zend */
                if (! $package instanceof CompletePackageInterface || ! ExtensionType::isValid($package->getType())) {
                    return false;
                }

                /** Return if the package extension name (derived or explicit) matches what we are looking for */
                return Package::fromComposerCompletePackage($package)->extensionName()->name() === $normalisedExtensionNameIfValid->name();
            },
        );

        if (! count($matches)) {
            throw new OutOfRangeException('No matches found for ' . $searchTerm);
        }

        usort($matches, static function (array $a, array $b): int {
            return (array_key_exists('downloads', $b) ? $b['downloads'] : 0)
                <=> (array_key_exists('downloads', $a) ? $a['downloads'] : 0);
        });

        return $matches;
    }

    /** @return MatchingPackages */
    public function byProvider(Composer $pieComposer, ExtensionName $extensionName): array
    {
        $matches = [];
        foreach ($pieComposer->getRepositoryManager()->getRepositories() as $repo) {
            $matches = array_merge($matches, $repo->getProviders($extensionName->nameWithExtPrefix()));
        }

        return $this->filterToCompatible($pieComposer, array_values($matches), $extensionName->name());
    }

    /**
     * @param non-empty-string $searchTerm
     *
     * @return MatchingPackages
     */
    public function bySearching(Composer $pieComposer, string $searchTerm): array
    {
        $matches = [];
        foreach ($pieComposer->getRepositoryManager()->getRepositories() as $repo) {
            $matches = array_merge($matches, $repo->search($searchTerm, RepositoryInterface::SEARCH_FULLTEXT, 'php-ext'));
            $matches = array_merge($matches, $repo->search($searchTerm, RepositoryInterface::SEARCH_FULLTEXT, 'php-ext-zend'));
        }

        return $this->filterToCompatible($pieComposer, $matches, $searchTerm);
    }
}
