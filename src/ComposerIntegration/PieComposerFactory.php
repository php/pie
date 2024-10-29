<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Factory;
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\PartialComposer;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use Php\Pie\Building\Build;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\Install;
use Psr\Container\ContainerInterface;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class PieComposerFactory extends Factory
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly PieComposerRequest $composerRequest,
    ) {
    }

    protected function createDefaultInstallers(Installer\InstallationManager $im, PartialComposer $composer, IOInterface $io, ProcessExecutor|null $process = null): void
    {
        $fs = new Filesystem($process);

        $installerFactory = function (ExtensionType $type) use ($io, $composer, $fs): PiePackageInstaller {
            return new PiePackageInstaller(
                $io,
                $composer,
                $type,
                $fs,
                $this->container->get(Build::class),
                $this->container->get(Install::class),
                $this->composerRequest,
            );
        };

        $im->addInstaller($installerFactory(ExtensionType::PhpModule));
        $im->addInstaller($installerFactory(ExtensionType::ZendExtension));
    }
}
