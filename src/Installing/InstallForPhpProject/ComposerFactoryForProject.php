<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\Composer;
use Composer\Factory as ComposerFactory;
use Composer\IO\ConsoleIO;
use Composer\Package\RootPackageInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class ComposerFactoryForProject
{
    private Composer|null $memoizedComposer = null;

    public function composer(InputInterface $input, OutputInterface $output): Composer
    {
        if ($this->memoizedComposer === null) {
            $this->memoizedComposer = ComposerFactory::create(new ConsoleIO(
                $input,
                $output,
                new HelperSet([]),
            ));
        }

        return $this->memoizedComposer;
    }

    public function rootPackage(InputInterface $input, OutputInterface $output): RootPackageInterface
    {
        return $this->composer($input, $output)->getPackage();
    }
}
