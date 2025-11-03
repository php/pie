<?php

declare(strict_types=1);

use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionParser;
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

$phpBinaryPath        = PhpBinaryPath::fromPhpConfigExecutable($phpConfigPath);
$phpVersionConstraint = (new VersionParser())->parseConstraints($phpBinaryPath->version());

$packageNames = array_map(
    static fn (PackageInterface $package): string => $package->getName(),
    array_filter(
        BundledPhpExtensionsRepository::forTargetPlatform(
            TargetPlatform::fromPhpBinaryPath(
                $phpBinaryPath,
                null,
            ),
        )
            ->getPackages(),
        static function (PackageInterface $package) use ($phpVersionConstraint): bool {
            $requires = $package->getRequires();

            return ! array_key_exists('php', $requires)
                || $requires['php']->getConstraint()->matches($phpVersionConstraint);
        },
    ),
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

echo Process::run([$phpBinaryPath->phpBinaryPath, '-m'], timeout: 60);
echo Process::run(['bin/pie', 'show', '--with-php-config=' . $phpBinaryPath->phpConfigPath()], timeout: 60);

if ($anyFailures) {
    exit(1);
}
