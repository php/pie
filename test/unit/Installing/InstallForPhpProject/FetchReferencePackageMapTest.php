<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\InstallForPhpProject;

use Composer\Downloader\TransportException;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use DateTimeZone;
use Php\Pie\Installing\InstallForPhpProject\CachedPackageMap;
use Php\Pie\Installing\InstallForPhpProject\ExtensionToPackageSelections;
use Php\Pie\Installing\InstallForPhpProject\FetchReferencePackageMap;
use Php\Pie\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Safe\DateTimeImmutable;
use Symfony\Component\Clock\MockClock;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(FetchReferencePackageMap::class)]
final class FetchReferencePackageMapTest extends TestCase
{
    private const REFERENCE_LIST_URL = 'https://raw.githubusercontent.com/php/pie/HEAD/resources/package-map.json';

    private HttpDownloader&MockObject $httpDownloader;
    private Settings&MockObject $pieSettings;
    private MockClock $clock;
    private FetchReferencePackageMap $fetcher;

    public function setUp(): void
    {
        parent::setUp();

        $this->httpDownloader = $this->createMock(HttpDownloader::class);
        $this->pieSettings    = $this->createMock(Settings::class);
        $this->clock          = new MockClock('2026-10-20 15:00:00', new DateTimeZone('UTC'));

        $this->fetcher = new FetchReferencePackageMap(
            $this->httpDownloader,
            $this->pieSettings,
            $this->clock,
        );
    }

    public function testNewDownloadUpdatesCache(): void
    {
        $expiry = new DateTimeImmutable('2026-10-20 15:05:00', new DateTimeZone('UTC'));

        $this->pieSettings
            ->method('packageMap')
            ->willReturn(null);
        $this->pieSettings->expects(self::once())
            ->method('updatePackageMap')
            ->with(
                new CachedPackageMap(
                    ExtensionToPackageSelections::fromArrayMap(['foo' => 'foo/foo']),
                    '"etag123"',
                    $expiry,
                ),
            );

        $this->httpDownloader->expects(self::once())
            ->method('get')
            ->with(
                self::REFERENCE_LIST_URL,
                [
                    'http' => [
                        'method' => 'GET',
                        'header' => [],
                        'timeout' => 1,
                    ],
                ],
            )
            ->willReturn(new Response(
                ['url' => self::REFERENCE_LIST_URL],
                200,
                headers: [
                    'ETag: "etag123"',
                    'Cache-Control: max-age=300',
                ],
                body: json_encode(['foo' => 'foo/foo'], JSON_THROW_ON_ERROR),
            ));

        self::assertSame(
            ['foo' => 'foo/foo'],
            ($this->fetcher)()->toArray(),
        );
    }

    public function testCacheWithinMaxAgeDoesNotRequestNewMap(): void
    {
        $expiry = new DateTimeImmutable('2026-10-20 15:03:00', new DateTimeZone('UTC'));

        $this->pieSettings
            ->method('packageMap')
            ->willReturn(new CachedPackageMap(
                ExtensionToPackageSelections::fromArrayMap(['foo' => 'foo/foo']),
                '"etag123"',
                $expiry,
            ));
        $this->pieSettings->expects(self::never())
            ->method('updatePackageMap');

        $this->httpDownloader->expects(self::never())
            ->method('get');

        self::assertSame(
            ['foo' => 'foo/foo'],
            ($this->fetcher)()->toArray(),
        );
    }

    public function testGetWith304UsesExistingMapAndExpiryUpdated(): void
    {
        $oldExpiry = new DateTimeImmutable('2026-10-20 14:55:00', new DateTimeZone('UTC'));
        $newExpiry = new DateTimeImmutable('2026-10-20 15:05:00', new DateTimeZone('UTC'));

        $this->pieSettings
            ->method('packageMap')
            ->willReturn(new CachedPackageMap(
                ExtensionToPackageSelections::fromArrayMap(['foo' => 'foo/foo']),
                '"etag123"',
                $oldExpiry,
            ));
        $this->pieSettings->expects(self::once())
            ->method('updatePackageMap')
            ->with(
                new CachedPackageMap(
                    ExtensionToPackageSelections::fromArrayMap(['foo' => 'foo/foo']),
                    '"etag123"',
                    $newExpiry,
                ),
            );

        $this->httpDownloader->expects(self::once())
            ->method('get')
            ->with(
                self::REFERENCE_LIST_URL,
                [
                    'http' => [
                        'method' => 'GET',
                        'header' => ['If-None-Match: "etag123"'],
                        'timeout' => 1,
                    ],
                ],
            )
            ->willReturn(new Response(
                ['url' => self::REFERENCE_LIST_URL],
                304,
                headers: [
                    'ETag: "etag123"',
                    'Cache-Control: max-age=300',
                ],
                body: null,
            ));

        self::assertSame(
            ['foo' => 'foo/foo'],
            ($this->fetcher)()->toArray(),
        );
    }

    public function testGetWith200RefreshesMap(): void
    {
        $oldExpiry = new DateTimeImmutable('2026-10-20 14:55:00', new DateTimeZone('UTC'));
        $newExpiry = new DateTimeImmutable('2026-10-20 15:05:00', new DateTimeZone('UTC'));

        $this->pieSettings
            ->method('packageMap')
            ->willReturn(new CachedPackageMap(
                ExtensionToPackageSelections::fromArrayMap(['foo' => 'foo/foo', 'bar' => 'bar/bar']),
                '"etag123"',
                $oldExpiry,
            ));
        $this->pieSettings->expects(self::once())
            ->method('updatePackageMap')
            ->with(
                new CachedPackageMap(
                    ExtensionToPackageSelections::fromArrayMap(['foo' => 'foo2/foo2']),
                    '"etag456"',
                    $newExpiry,
                ),
            );

        $this->httpDownloader->expects(self::once())
            ->method('get')
            ->with(
                self::REFERENCE_LIST_URL,
                [
                    'http' => [
                        'method' => 'GET',
                        'header' => ['If-None-Match: "etag123"'],
                        'timeout' => 1,
                    ],
                ],
            )
            ->willReturn(new Response(
                ['url' => self::REFERENCE_LIST_URL],
                200,
                headers: [
                    'ETag: "etag456"',
                    'Cache-Control: max-age=300',
                ],
                body: json_encode(['foo' => 'foo2/foo2'], JSON_THROW_ON_ERROR),
            ));

        self::assertSame(
            ['foo' => 'foo2/foo2'],
            ($this->fetcher)()->toArray(),
        );
    }

    public function testGetWithErrorAndNoCacheReturnsEmptyMap(): void
    {
        $this->pieSettings
            ->method('packageMap')
            ->willReturn(null);
        $this->pieSettings->expects(self::never())
            ->method('updatePackageMap');

        $this->httpDownloader->expects(self::once())
            ->method('get')
            ->with(
                self::REFERENCE_LIST_URL,
                [
                    'http' => [
                        'method' => 'GET',
                        'header' => [],
                        'timeout' => 1,
                    ],
                ],
            )
            ->willThrowException(new TransportException('The "' . self::REFERENCE_LIST_URL . '" file could not be downloaded (HTTP/2 404)', 404));

        self::assertSame(
            [],
            ($this->fetcher)()->toArray(),
        );
    }

    public function testGetWithErrorWithCachedMapUsesExistingMap(): void
    {
        // even if expired, best guess
        $expiry = new DateTimeImmutable('2026-10-20 14:55:00', new DateTimeZone('UTC'));

        $this->pieSettings
            ->method('packageMap')
            ->willReturn(new CachedPackageMap(
                ExtensionToPackageSelections::fromArrayMap(['foo' => 'foo/foo']),
                '"etag123"',
                $expiry,
            ));
        $this->pieSettings->expects(self::never())
            ->method('updatePackageMap');

        $this->httpDownloader->expects(self::once())
            ->method('get')
            ->with(
                self::REFERENCE_LIST_URL,
                [
                    'http' => [
                        'method' => 'GET',
                        'header' => ['If-None-Match: "etag123"'],
                        'timeout' => 1,
                    ],
                ],
            )
            ->willThrowException(new TransportException('The "' . self::REFERENCE_LIST_URL . '" file could not be downloaded (HTTP/2 404)', 404));

        self::assertSame(
            ['foo' => 'foo/foo'],
            ($this->fetcher)()->toArray(),
        );
    }
}
