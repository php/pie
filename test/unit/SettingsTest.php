<?php

declare(strict_types=1);

namespace Php\PieUnitTest;

use Composer\Json\JsonValidationException;
use Composer\Util\Filesystem;
use DateTimeZone;
use JsonException;
use Php\Pie\Installing\InstallForPhpProject\CachedPackageMap;
use Php\Pie\Installing\InstallForPhpProject\ExtensionToPackageSelections;
use Php\Pie\SelfManage\Update\Channel;
use Php\Pie\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Safe\DateTimeImmutable;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

#[CoversClass(Settings::class)]
final class SettingsTest extends TestCase
{
    public function testReadingInvalidConfigThrowsException(): void
    {
        $workingDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_settings_test', true) . DIRECTORY_SEPARATOR;
        mkdir($workingDir, recursive: true);
        file_put_contents($workingDir . 'pie-settings.json', '{"channel":"surprise! not a valid value"}');

        $settings = new Settings($workingDir);

        $this->expectException(JsonValidationException::class);
        $settings->updateChannel();
    }

    public function testNewSettingsJsonCanBeCreated(): void
    {
        $workingDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_settings_test', true) . DIRECTORY_SEPARATOR;

        $settings = new Settings($workingDir);
        self::assertSame(Channel::Stable, $settings->updateChannel());

        $settings->changeUpdateChannel(Channel::Preview);
        self::assertSame(Channel::Preview, $settings->updateChannel());

        self::assertJsonStringEqualsJsonString(
            '{"channel": "preview"}',
            (string) file_get_contents($workingDir . 'pie-settings.json'),
        );

        (new Filesystem())->remove($workingDir);
    }

    public function testExistingSettingsCanBeUpdated(): void
    {
        $workingDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_settings_test', true) . DIRECTORY_SEPARATOR;
        mkdir($workingDir, recursive: true);
        file_put_contents($workingDir . 'pie-settings.json', '{"channel": "stable"}');

        $settings = new Settings($workingDir);
        self::assertSame(Channel::Stable, $settings->updateChannel());

        $settings->changeUpdateChannel(Channel::Nightly);
        self::assertSame(Channel::Nightly, $settings->updateChannel());

        self::assertJsonStringEqualsJsonString(
            '{"channel": "nightly"}',
            (string) file_get_contents($workingDir . 'pie-settings.json'),
        );

        (new Filesystem())->remove($workingDir);
    }

    public function testPackageMapIsNullWhenNotCached(): void
    {
        $workingDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_settings_test', true) . DIRECTORY_SEPARATOR;

        self::assertNull((new Settings($workingDir))->packageMap());

        (new Filesystem())->remove($workingDir);
    }

    public function testPackageMapCanBeWrittenAndReadBack(): void
    {
        $workingDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_settings_test', true) . DIRECTORY_SEPARATOR;
        $cached     = new CachedPackageMap(
            ExtensionToPackageSelections::fromArrayMap(['foo' => 'foo/foo']),
            '"etag123"',
            new DateTimeImmutable('2026-10-20 15:05:00', new DateTimeZone('UTC')),
        );

        (new Settings($workingDir))->updatePackageMap($cached);

        self::assertJsonStringEqualsJsonString(
            '{"package_map": {"map": {"foo": "foo/foo"}, "etag": "\"etag123\"", "expires": "2026-10-20T15:05:00+00:00"}}',
            (string) file_get_contents($workingDir . 'pie-settings.json'),
        );
        self::assertEquals($cached, (new Settings($workingDir))->packageMap());

        (new Filesystem())->remove($workingDir);
    }

    public function testUpdatingPackageMapKeepsExistingChannel(): void
    {
        $workingDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_settings_test', true) . DIRECTORY_SEPARATOR;
        mkdir($workingDir, recursive: true);
        file_put_contents($workingDir . 'pie-settings.json', '{"channel": "nightly"}');

        $settings = new Settings($workingDir);
        $settings->updatePackageMap(new CachedPackageMap(
            ExtensionToPackageSelections::fromArrayMap(['foo' => 'foo/foo']),
            '"etag123"',
            new DateTimeImmutable('2026-10-20 15:05:00', new DateTimeZone('UTC')),
        ));

        self::assertSame(Channel::Nightly, $settings->updateChannel());

        (new Filesystem())->remove($workingDir);
    }

    /** @return array<non-empty-string, array{non-empty-string}> */
    public static function corruptPackageMapProvider(): array
    {
        return [
            'missing map' => ['{"package_map": {"etag": "\"etag123\"", "expires": "2026-10-20T15:05:00+00:00"}}'],
            'missing etag' => ['{"package_map": {"map": {"foo": "foo/foo"}, "expires": "2026-10-20T15:05:00+00:00"}}'],
            'missing expires' => ['{"package_map": {"map": {"foo": "foo/foo"}, "etag": "\"etag123\""}}'],
            'expires is not a date' => ['{"package_map": {"map": {"foo": "foo/foo"}, "etag": "\"etag123\"", "expires": "not a date"}}'],
            'expires is not ATOM format' => ['{"package_map": {"map": {"foo": "foo/foo"}, "etag": "\"etag123\"", "expires": "2026-10-20 15:05:00"}}'],
            'empty package name' => ['{"package_map": {"map": {"foo": ""}, "etag": "\"etag123\"", "expires": "2026-10-20T15:05:00+00:00"}}'],
            'empty extension name' => ['{"package_map": {"map": {"": "foo/foo"}, "etag": "\"etag123\"", "expires": "2026-10-20T15:05:00+00:00"}}'],
            'map is a string' => ['{"package_map": {"map": "foo/foo", "etag": "\"etag123\"", "expires": "2026-10-20T15:05:00+00:00"}}'],
            'etag is a number' => ['{"package_map": {"map": {"foo": "foo/foo"}, "etag": 123, "expires": "2026-10-20T15:05:00+00:00"}}'],
        ];
    }

    #[DataProvider('corruptPackageMapProvider')]
    public function testCorruptPackageMapIsTreatedAsNotCached(string $settingsJson): void
    {
        $workingDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_settings_test', true) . DIRECTORY_SEPARATOR;
        mkdir($workingDir, recursive: true);
        file_put_contents($workingDir . 'pie-settings.json', $settingsJson);

        self::assertNull((new Settings($workingDir))->packageMap());

        (new Filesystem())->remove($workingDir);
    }

    public function testInvalidJsonWhenReadingPackageMapThrowsException(): void
    {
        $workingDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_settings_test', true) . DIRECTORY_SEPARATOR;
        mkdir($workingDir, recursive: true);
        file_put_contents($workingDir . 'pie-settings.json', '{"package_map":');

        $settings = new Settings($workingDir);

        $this->expectException(JsonException::class);
        $settings->packageMap();
    }
}
