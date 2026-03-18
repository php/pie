#!/usr/bin/env php
<?php

declare(strict_types=1);

// echo "James says hello :)\n";

// Workaround for https://github.com/php/pie/issues/537 and https://github.com/box-project/box/issues/1577
error_reporting(error_reporting() & ~E_DEPRECATED);

Phar::mapPhar('pie.phar');

require 'phar://pie.phar/.box/bin/check-requirements.php';

$_SERVER['SCRIPT_FILENAME'] = 'phar://pie.phar/bin/pie';
require 'phar://pie.phar/bin/pie';

// phpcs:ignore Generic.Files.InlineHTML.Found
__HALT_COMPILER(); ?>
