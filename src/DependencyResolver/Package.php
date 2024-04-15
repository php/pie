<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Package\CompletePackageInterface;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
final class Package
{
    public const TYPE_PHP_MODULE     = 'php-ext';
    public const TYPE_ZEND_EXTENSION = 'php-ext-zend';

    private function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly string|null $downloadUrl,
    ) {
    }

    public static function fromComposerCompletePackage(CompletePackageInterface $completePackage): self
    {
        return new self(
            $completePackage->getPrettyName(),
            $completePackage->getPrettyVersion(),
            $completePackage->getDistUrl(),
        );
    }
}
