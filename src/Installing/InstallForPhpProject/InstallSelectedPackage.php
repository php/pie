<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallForPhpProject;

use Php\Pie\Command\InvokeSubCommand;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

use function array_map;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class InstallSelectedPackage
{
    public function __construct(
        private readonly InvokeSubCommand $invokeSubCommand,
    ) {
    }

    /** @param list<RequestedPackageAndVersion> $selectedPackages */
    public function withSubCommand(
        array $selectedPackages,
        Command $command,
        InputInterface $input,
    ): int {
        $params = [
            'command' => 'install',
            'requested-package-and-version' => [
                ...array_map(
                    static fn (RequestedPackageAndVersion $package) => $package->prettyNameAndVersion(),
                    $selectedPackages,
                ),
            ],
        ];

        return ($this->invokeSubCommand)(
            $command,
            $params,
            $input,
        );
    }
}
