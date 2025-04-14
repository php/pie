<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Composer\Factory as ComposerFactory;
use Composer\IO\ConsoleIO;
use Composer\Package\RootPackageInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FindRootPackage
{
    public function forCwd(InputInterface $input, OutputInterface $output): RootPackageInterface
    {
        $io = new ConsoleIO($input, $output, new HelperSet([]));

        return ComposerFactory::create($io)->getPackage();
    }
}
