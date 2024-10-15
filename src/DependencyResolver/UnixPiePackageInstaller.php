<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\PartialComposer;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use Php\Pie\ExtensionType;
use Webmozart\Assert\Assert;

class UnixPiePackageInstaller extends LibraryInstaller
{
    /**
     * @param 'php-ext'|'php-ext-zend' $type
     */
    public function __construct(IOInterface $io, PartialComposer $composer, string $type, Filesystem $filesystem)
    {
        Assert::oneOf($type, [ExtensionType::PhpModule->value, ExtensionType::ZendExtension->value]);

        parent::__construct($io, $composer, $type, $filesystem);
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        return parent::install($repo, $package)
            ->then(function () use ($repo, $package) {
                $downloadPath = $this->getInstallPath($package);
                $this->io->write('Downloaded to: ' . $downloadPath);
                $this->io->write('AFTER INSTALL COMPLETE ' . $package->getPrettyName());
            });
    }
}
