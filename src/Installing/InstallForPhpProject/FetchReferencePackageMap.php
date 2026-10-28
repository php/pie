<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\Downloader\TransportException;
use Composer\Pcre\Preg;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Composer\Util\Platform;
use DateInterval;
use Php\Pie\Settings;
use Psr\Clock\ClockInterface;
use Webmozart\Assert\Assert;

use function json_decode;
use function Safe\file_get_contents;

use const JSON_THROW_ON_ERROR;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class FetchReferencePackageMap
{
    private const REFERENCE_LIST_URL = 'https://raw.githubusercontent.com/php/pie/HEAD/resources/package-map.json';
    private const TIMEOUT_SECONDS    = 1;
    private const BUNDLED_MAP_PATH   = __DIR__ . '/../../../resources/package-map.json';

    public function __construct(
        private readonly HttpDownloader $httpDownloader,
        private readonly Settings $pieSettings,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(): ExtensionToPackageSelections
    {
        $cached = $this->pieSettings->packageMap();
        $now    = $this->clock->now();

        if ($cached !== null && $now < $cached->expiry) {
            return $cached->map;
        }

        if ((bool) Platform::getEnv('PIE_DISABLE_PACKAGE_MAP_FETCH')) {
            return $cached->map ?? self::bundledMap();
        }

        try {
            $response = $this->httpDownloader->get(
                self::REFERENCE_LIST_URL,
                [
                    'http' => [
                        'method' => 'GET',
                        'header' => $cached !== null ? ['If-None-Match: ' . $cached->etag] : [],
                        'timeout' => self::TIMEOUT_SECONDS,
                    ],
                ],
            );
        } catch (TransportException) {
            return $cached->map ?? self::bundledMap();
        }

        $expiry = $now->add(new DateInterval('PT' . self::maxAgeSeconds($response) . 'S'));

        if ($response->getStatusCode() === 304 && $cached !== null) {
            $this->pieSettings->updatePackageMap(new CachedPackageMap($cached->map, $cached->etag, $expiry));

            return $cached->map;
        }

        $decoded = $response->decodeJson();
        Assert::isMap($decoded);
        Assert::allString($decoded);
        $map = ExtensionToPackageSelections::fromArrayMap($decoded);

        $etag = $response->getHeader('etag');
        if ($etag !== null && $etag !== '') {
            $this->pieSettings->updatePackageMap(new CachedPackageMap($map, $etag, $expiry));
        }

        return $map;
    }

    private static function bundledMap(): ExtensionToPackageSelections
    {
        $decoded = json_decode(file_get_contents(self::BUNDLED_MAP_PATH), true, flags: JSON_THROW_ON_ERROR);
        Assert::isMap($decoded);
        Assert::allString($decoded);

        return ExtensionToPackageSelections::fromArrayMap($decoded);
    }

    private static function maxAgeSeconds(Response $response): int
    {
        if (Preg::isMatch('{max-age=(\d+)}', (string) $response->getHeader('cache-control'), $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
