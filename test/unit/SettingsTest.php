<?php

declare(strict_types=1);

namespace Php\PieUnitTest;

use Composer\Json\JsonValidationException;
use Composer\Util\Filesystem;
use Php\Pie\SelfManage\Update\Channel;
use Php\Pie\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;
use function mkdir;
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
}
