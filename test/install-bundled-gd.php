<?php

declare(strict_types=1);

use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Util\Process;

require __DIR__ . '/../vendor/autoload.php';

$phpConfigPath = $argv[1] ?? '';
$fontPath      = $argv[2] ?? '';

if ($phpConfigPath === '' || $fontPath === '') {
    echo 'Usage: ' . __FILE__ . ' <php-config-path> <TrueType-font-path>' . PHP_EOL;
    exit(1);
}

$phpBinaryPath = PhpBinaryPath::fromPhpConfigExecutable($phpConfigPath);

foreach (['default' => [], 'configured' => ['--with-jpeg', '--with-freetype']] as $variant => $options) {
    echo Process::run([
        $phpBinaryPath->phpBinaryPath,
        __DIR__ . '/../bin/pie',
        'install',
        'php/gd',
        '--force',
        '--no-interaction',
        '--with-php-config=' . $phpBinaryPath->phpConfigPath(),
        '-v',
        '-j2',
        ...$options,
    ], timeout: null) . PHP_EOL;

    // Start a new PHP process so it loads the extension just installed by PIE.
    echo Process::run([
        $phpBinaryPath->phpBinaryPath,
        __DIR__ . '/assets/check-bundled-gd.php',
        $variant,
        $fontPath,
    ]) . PHP_EOL;
}
