<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\Composer;
use Composer\Repository\RepositoryInterface;
use OutOfRangeException;
use Php\Pie\ExtensionName;

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
    public function for(Composer $pieComposer, ExtensionName $extension): array
    {
        $matches = [];
        foreach ($pieComposer->getRepositoryManager()->getRepositories() as $repo) {
            $matches = array_merge($matches, $repo->search($extension->name(), RepositoryInterface::SEARCH_FULLTEXT, 'php-ext'));
            $matches = array_merge($matches, $repo->search($extension->name(), RepositoryInterface::SEARCH_FULLTEXT, 'php-ext-zend'));
        }

        if (! count($matches)) {
            throw new OutOfRangeException('No matches found for ' . $extension->name());
        }

        usort($matches, static function (array $a, array $b): int {
            return $b['downloads'] <=> $a['downloads'];
        });

        return $matches;
    }
}
