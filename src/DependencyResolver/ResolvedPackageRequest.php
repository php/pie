<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Php\Pie\ExtensionName;

use function array_map;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ResolvedPackageRequest
{
    public function __construct(
        public readonly Package $piePackage,
        public readonly RequestedPackageAndVersion $requestedPackageAndVersion,
    ) {
    }

    /**
     * @param list<self> $resolvedPackageRequests
     *
     * @return list<ExtensionName>
     */
    public static function extensionNames(array $resolvedPackageRequests): array
    {
        return array_map(
            static fn (self $resolvedPackageRequest): ExtensionName => $resolvedPackageRequest->piePackage->extensionName(),
            $resolvedPackageRequests,
        );
    }

    /**
     * @param list<self> $resolvedPackageRequests
     *
     * @return list<string>
     */
    public static function requestedPackageNames(array $resolvedPackageRequests): array
    {
        return array_map(
            static fn (self $resolvedPackageRequest): string => $resolvedPackageRequest->requestedPackageAndVersion->package,
            $resolvedPackageRequests,
        );
    }

    /**
     * @param non-empty-list<self> $resolvedPackageRequests
     *
     * @return non-empty-list<Package>
     */
    public static function piePackages(array $resolvedPackageRequests): array
    {
        return array_map(
            static fn (self $resolvedPackageRequest): Package => $resolvedPackageRequest->piePackage,
            $resolvedPackageRequests,
        );
    }
}
