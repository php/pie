<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\PartialComposer;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use Php\Pie\Building\UnixBuild;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionType;
use Symfony\Component\Console\Output\BufferedOutput;
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
                $pieContext = $this->composer->getConfig()->get('__PIE__');

                if ($pieContext['packageName'] !== $package->getName()) {
                    $this->io->write(
                        sprintf(
                            'Not using PIE to install %s as it was not the expected package %s',
                            $package->getName(),
                            $pieContext['packageName'],
                        ),
                        true,
                        IOInterface::VERBOSE,
                    );
                    return;
                }

                if (! $package instanceof CompletePackage) {
                    $this->io->write(
                        sprintf(
                            'Not using PIE to install %s as it was not a Complete Package',
                            $package->getName(),
                        ),
                        true,
                        IOInterface::VERBOSE,
                    );
                    return;
                }

                $downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
                    Package::fromComposerCompletePackage($package),
                    $this->getInstallPath($package)
                );

                if ($pieContext['install'] || $pieContext['build']) {
                    $bo = new BufferedOutput();

                    $builder = new UnixBuild();
                    $builder(
                        $downloadedPackage,
                        $pieContext['targetPlatform'],
                        [], // @todo configure options
                        $bo
                    );
                }

                return;

                return;
//                $b = new UnixBuild();
//                $b->__invoke(
//                    $downloadedPackage,
//
//
//                );
            });
    }

    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        return parent::update($repo, $initial, $target)
            ->then(function () use ($repo, $initial, $target) {
                $this->io->write('LOL');
            });
    }

    public function cleanup($type, PackageInterface $package, ?PackageInterface $prevPackage = null)
    {
        return parent::cleanup($type, $package, $prevPackage)
            ->then(function () {
                $this->io->write('CLEANUP');
            });
    }

}
