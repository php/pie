<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Php\Pie\Command\InvokeSubCommand;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\ExtensionName;
use Php\Pie\Util\OutputFormatterWithPrefix;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class InstallSelectedPackage
{
    public function __construct(
        private readonly InvokeSubCommand $invokeSubCommand,
    ) {
    }

    public function withSubCommand(
        ExtensionName $ext,
        RequestedPackageAndVersion $selectedPackage,
        Command $command,
        InputInterface $input,
    ): int {
        $params = [
            'command' => 'install',
            'requested-package-and-version' => [$selectedPackage->prettyNameAndVersion()],
        ];

        return ($this->invokeSubCommand)(
            $command,
            $params,
            $input,
            OutputFormatterWithPrefix::newWithPrefix('  ' . $ext->name() . '> '),
        );
    }
}
