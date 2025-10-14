<?php

declare(strict_types=1);

namespace Php\Pie;

use Composer\Json\JsonFile;
use Php\Pie\SelfManage\Update\Channel;

use function array_key_exists;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function mkdir;
use function rtrim;

use const DIRECTORY_SEPARATOR;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @phpstan-type PieSettings = array{
 *     channel?: non-empty-string,
 * }
 */
class Settings
{
    private const PIE_SETTINGS_SCHEMA_FILE_NAME = __DIR__ . '/../resources/pie-settings-schema.json';
    private const PIE_SETTINGS_FILE_NAME        = 'pie-settings.json';

    public function __construct(private readonly string $pieWorkingDirectory)
    {
    }

    private function pieSettingsFullPath(): string
    {
        $workDir = rtrim($this->pieWorkingDirectory, DIRECTORY_SEPARATOR);

        if (! file_exists($workDir)) {
            mkdir($workDir, recursive: true);
        }

        return $workDir . DIRECTORY_SEPARATOR . self::PIE_SETTINGS_FILE_NAME;
    }

    /** @phpstan-assert PieSettings $settingsBlob */
    private function validateSchema(mixed $settingsBlob): void
    {
        JsonFile::validateJsonSchema(
            self::PIE_SETTINGS_FILE_NAME,
            $settingsBlob,
            JsonFile::STRICT_SCHEMA,
            self::PIE_SETTINGS_SCHEMA_FILE_NAME,
        );
    }

    /** @phpstan-return PieSettings */
    private function read(): array
    {
        $pieSettingsFileName = $this->pieSettingsFullPath();
        if (! file_exists($pieSettingsFileName)) {
            return [];
        }

        $content = file_get_contents($pieSettingsFileName);
        if ($content === false) {
            return [];
        }

        $config = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        $this->validateSchema($config);

        return $config;
    }

    /** @param array<array-key, mixed> $config */
    private function write(array $config): void
    {
        $this->validateSchema($config);

        file_put_contents($this->pieSettingsFullPath(), json_encode($config, flags: JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public function updateChannel(): Channel
    {
        $config = $this->read();

        return array_key_exists('channel', $config)
            ? Channel::from($config['channel'])
            : Channel::Stable;
    }

    public function changeUpdateChannel(Channel $channel): void
    {
        $config = $this->read();

        $config['channel'] = $channel->value;

        $this->write($config);
    }
}
