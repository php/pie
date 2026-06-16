<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Php\Pie\ExtensionName;
use RuntimeException;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @phpstan-import-type MatchingPackages from FindMatchingPackages
 */
class PackageSelectionRequired extends RuntimeException
{
    /** @param MatchingPackages $matches */
    private function __construct(
        public readonly ExtensionName $extensionName,
        public readonly array $matches,
    ) {
        parent::__construct('A package selection is required for ' . $extensionName->nameWithExtPrefix());
    }

    /** @param MatchingPackages $matches */
    public static function forExtensionWithMatches(ExtensionName $extensionName, array $matches): self
    {
        return new self($extensionName, $matches);
    }
}
