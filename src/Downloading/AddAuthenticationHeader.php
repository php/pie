<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Util\AuthHelper;
use Php\Pie\DependencyResolver\Package;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

use function array_map;
use function array_walk;
use function count;
use function explode;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class AddAuthenticationHeader
{
    public static function withAuthHeaderFromComposer(RequestInterface $request, Package $package, AuthHelper $authHelper): RequestInterface
    {
        if ($package->downloadUrl === null) {
            throw new RuntimeException(sprintf('The package %s does not have a download URL', $package->name));
        }

        $authHeaders = $authHelper->addAuthenticationHeader([], 'github.com', $package->downloadUrl);
        array_walk(
            $authHeaders,
            static function (string $v) use (&$request): void {
                $headerParts = array_map('trim', explode(':', $v, 2));

                if (count($headerParts) !== 2 || ! $headerParts[0] || ! $headerParts[1]) {
                    throw new RuntimeException('Authorization header is malformed, it should contain a non-empty key and a non-empty value.');
                }

                $request = $request->withHeader($headerParts[0], $headerParts[1]);
            },
        );

        return $request;
    }
}
