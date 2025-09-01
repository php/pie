<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Repository\RepositoryInterface;
use OutOfRangeException;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;

use function array_filter;
use function array_key_exists;
use function array_merge;
use function count;
use function usort;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @psalm-type MatchingPackages = list<array{name: string, description: ?string, ...}>
 */
class FindMatchingPackages
{
    /** @return MatchingPackages */
    public function for(Composer $pieComposer, string $searchTerm, string $stringConstraint): array
    {
        $matches = [];
        foreach ($pieComposer->getRepositoryManager()->getRepositories() as $repo) {
            $matches = array_merge($matches, $repo->search($searchTerm, RepositoryInterface::SEARCH_FULLTEXT, 'php-ext'));
            $matches = array_merge($matches, $repo->search($searchTerm, RepositoryInterface::SEARCH_FULLTEXT, 'php-ext-zend'));
        }

        if (ExtensionName::isValidExtensionName($searchTerm)) {
            $extensionName = ExtensionName::normaliseFromString($searchTerm);

            $matches = array_filter(
                $matches,
                static function (array $match) use ($pieComposer, $extensionName, $stringConstraint): bool {
                    $package = $pieComposer->getRepositoryManager()->findPackage($match['name'], $stringConstraint);

                    if (! $package instanceof CompletePackage) {
                        return false;
                    }

                    return Package::fromComposerCompletePackage($package)->extensionName()->name() === $extensionName->name();
                },
            );
        }

        if (! count($matches)) {
            throw new OutOfRangeException('No matches found for ' . $searchTerm);
        }

        usort($matches, static function (array $a, array $b): int {
            return (array_key_exists('downloads', $b) ? $b['downloads'] : 0)
                <=> (array_key_exists('downloads', $a) ? $a['downloads'] : 0);
        });

        return $matches;
    }
}
