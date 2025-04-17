<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\Factory as ComposerFactory;
use Composer\IO\ConsoleIO;
use Composer\Package\RootPackageInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class FindRootPackage
{
    public function forCwd(InputInterface $input, OutputInterface $output): RootPackageInterface
    {
        $io = new ConsoleIO($input, $output, new HelperSet([]));

        return ComposerFactory::create($io)->getPackage();
    }
}
