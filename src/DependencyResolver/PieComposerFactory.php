<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Factory;
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\RootPackageInterface;
use Composer\PartialComposer;
use Composer\Repository;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use Php\Pie\ExtensionType;
use Psr\Container\ContainerInterface;

class PieComposerFactory extends Factory
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    protected function createDefaultInstallers(Installer\InstallationManager $im, PartialComposer $composer, IOInterface $io, ?ProcessExecutor $process = null): void
    {
        $fs = new Filesystem($process);

        $im->addInstaller(new UnixPiePackageInstaller($io, $composer, ExtensionType::PhpModule->value, $fs));
//        $im->addInstaller(new UnixPiePackageInstaller2($io, $composer, ExtensionType::PhpModule->value, $fs));
        $im->addInstaller(new UnixPiePackageInstaller($io, $composer, ExtensionType::ZendExtension->value, $fs));
        // @todo Windows installer
//        $binaryInstaller = new Installer\BinaryInstaller($io, rtrim($composer->getConfig()->get('bin-dir'), '/'), $composer->getConfig()->get('bin-compat'), $fs, rtrim($composer->getConfig()->get('vendor-dir'), '/'));
//
//        $im->addInstaller(new Installer\LibraryInstaller($io, $composer, null, $fs, $binaryInstaller));
//        $im->addInstaller(new Installer\PluginInstaller($io, $composer, $fs, $binaryInstaller));
//        $im->addInstaller(new Installer\MetapackageInstaller($io));
    }

    /**
     * @param Repository\RepositoryManager $rm
     */
    protected function addLocalRepository(IOInterface $io, Repository\RepositoryManager $rm, string $vendorDir, RootPackageInterface $rootPackage, ?ProcessExecutor $process = null): void
    {
        $fs = null;
        if ($process) {
            $fs = new Filesystem($process);
        }

        $rm->setLocalRepository(new Repository\InstalledFilesystemRepository(new JsonFile($vendorDir.'/composer/installed.json', null, $io), true, $rootPackage, $fs));
    }
}
