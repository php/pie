#!/usr/bin/env php
<?php

declare(strict_types=1);

// echo "James says hello :)\n";

// Workaround for https://github.com/php/pie/issues/537 and https://github.com/box-project/box/issues/1577
error_reporting(error_reporting() & ~E_DEPRECATED);

(static function (): void {
    $checkerSetting = getenv('BOX_REQUIREMENT_CHECKER');
    if (is_string($checkerSetting) && in_array(strtolower($checkerSetting), ['0', 'false'], true)) {
        return;
    }

    $errors = [];

    if (PHP_VERSION_ID < 80100 || PHP_VERSION_ID >= 80700) {
        $errors[] = sprintf('PIE requires PHP 8.1 to 8.6, but you are using PHP %s.', PHP_VERSION);
    }

    foreach (['filter', 'openssl'] as $extension) {
        if (extension_loaded($extension)) {
            continue;
        }

        $errors[] = sprintf('PIE requires the "%s" extension, but it is not loaded.', $extension);
    }

    if (! extension_loaded('mbstring') && ! extension_loaded('iconv')) {
        $errors[] = 'PIE requires either the "mbstring" or "iconv" extension, but neither is loaded.';
    }

    if ($errors === []) {
        return;
    }

    fwrite(STDERR, "Your system is not ready to run PIE:\n\n * " . implode("\n * ", $errors) . "\n\nTo try running PIE anyway, set the environment variable BOX_REQUIREMENT_CHECKER=0\n");
    exit(1);
})();

Phar::mapPhar('pie.phar');

$_SERVER['SCRIPT_FILENAME'] = 'phar://pie.phar/bin/pie';
require 'phar://pie.phar/bin/pie';

// phpcs:ignore Generic.Files.InlineHTML.Found
__HALT_COMPILER(); ?>
