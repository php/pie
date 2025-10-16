<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\Composer;
use Composer\Factory as ComposerFactory;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class ComposerFactoryForProject
{
    private Composer|null $memoizedComposer = null;

    public function composer(IOInterface $io): Composer
    {
        if ($this->memoizedComposer === null) {
            $this->memoizedComposer = ComposerFactory::create($io);
        }

        return $this->memoizedComposer;
    }

    public function rootPackage(IOInterface $io): RootPackageInterface
    {
        return $this->composer($io)->getPackage();
    }
}
