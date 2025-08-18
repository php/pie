<?php

declare(strict_types=1);

use Composer\Package\PackageInterface;
use Php\Pie\ComposerIntegration\BundledPhpExtensionsRepository;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Process;

require __DIR__ . '/../vendor/autoload.php';

$phpConfigPath = $argv[1] ?? '';

if ($phpConfigPath === '') {
    echo 'Usage: ' . __FILE__ . ' <php-config-path>' . PHP_EOL;
    exit(1);
}

$phpBinaryPath = PhpBinaryPath::fromPhpConfigExecutable($phpConfigPath);

$packageNames = array_map(
    static fn (PackageInterface $package): string => $package->getName(),
    BundledPhpExtensionsRepository::forTargetPlatform(
        TargetPlatform::fromPhpBinaryPath(
            $phpBinaryPath,
            null,
        ),
    )
        ->getPackages(),
);

$anyFailures = false;

foreach ($packageNames as $packageName) {
    $cmd = [
        'sudo',
        'bin/pie',
        'install',
        $packageName . ':@dev',
        '--with-php-config=' . $phpBinaryPath->phpConfigPath(),
    ];

    echo ' - ' . implode(' ', $cmd) . PHP_EOL;

    try {
        Process::run($cmd, timeout: null);
    } catch (Throwable $e) {
        echo $e->__toString() . PHP_EOL;
        $anyFailures = true;
    }
}

echo Process::run(['bin/pie', 'show', '--with-php-config=' . $phpBinaryPath->phpConfigPath()], timeout: 60);

if ($anyFailures) {
    exit(1);
}
