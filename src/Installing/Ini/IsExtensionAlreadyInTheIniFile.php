<?php

declare(strict_types=1);

namespace Php\Pie\Installing\Ini;

use Php\Pie\ExtensionName;

use function array_key_exists;
use function array_merge;
use function file;
use function in_array;
use function is_string;
use function parse_ini_string;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class IsExtensionAlreadyInTheIniFile
{
    public function __invoke(string $iniFilePath, ExtensionName $extensionName): bool
    {
        $loadedExts = $this->readIniFile($iniFilePath);

        return in_array($extensionName->name(), array_merge($loadedExts['extensions'], $loadedExts['zend_extensions']));
    }

    /** @return array{extensions: list<non-empty-string>, zend_extensions: list<non-empty-string>} */
    private function readIniFile(string $iniFilePath): array
    {
        $iniFileContentLines = file($iniFilePath);

        $extensions     = [];
        $zendExtensions = [];
        foreach ($iniFileContentLines as $line) {
            $lineIni = parse_ini_string($line);

            if (array_key_exists('extension', $lineIni) && is_string($lineIni['extension']) && $lineIni['extension'] !== '') {
                $extensions[] = $lineIni['extension'];
            }

            if (! array_key_exists('zend_extension', $lineIni) || ! is_string($lineIni['zend_extension']) || $lineIni['zend_extension'] === '') {
                continue;
            }

            $zendExtensions[] = $lineIni['zend_extension'];
        }

        return [
            'extensions' => $extensions,
            'zend_extensions' => $zendExtensions,
        ];
    }
}
