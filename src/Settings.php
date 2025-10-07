<?php

declare(strict_types=1);

namespace Php\Pie;

use Php\Pie\SelfManage\Update\Channel;

use function array_key_exists;
use function file_exists;
use function file_get_contents;
use function is_array;
use function json_decode;

use const DIRECTORY_SEPARATOR;

class Settings
{
    private const PIE_SETTINGS_FILE_NAME = 'pie-settings.json';

    public function __construct(private readonly string $pieWorkingDirectory)
    {
    }

    /**
     * @return array{
     *     channel?: non-empty-string,
     * }
     */
    private function read(): array
    {
        $pieSettingsFileName = $this->pieWorkingDirectory . DIRECTORY_SEPARATOR . self::PIE_SETTINGS_FILE_NAME;
        if (! file_exists($pieSettingsFileName)) {
            return [];
        }

        $content = file_get_contents($pieSettingsFileName);
        if ($content === false) {
            return [];
        }

        $config = json_decode($content, true);

        // @todo schema validation

        return is_array($config) ? $config : [];
    }

    public function updateChannel(): Channel
    {
        $config = $this->read();

        return array_key_exists('channel', $config)
            ? Channel::from($config['channel'])
            : Channel::Stable;
    }
}
