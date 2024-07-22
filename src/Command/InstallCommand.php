<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Php\Pie\Building\Build;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\Downloading\DownloadAndExtract;
use Php\Pie\Installing\Install;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'install',
    description: 'Download, build, and install a PIE-compatible PHP extension.',
)]
final class InstallCommand extends Command
{
    public function __construct(
        private readonly DependencyResolver $dependencyResolver,
        private readonly DownloadAndExtract $downloadAndExtract,
        private readonly Build $build,
        private readonly Install $install,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        CommandHelper::configureOptions($this);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! TargetPlatform::isRunningAsRoot()) {
            $output->writeln('This command needs elevated privileges, and may prompt you for your password.');
        }

        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $output);

        $requestedNameAndVersionPair = CommandHelper::requestedNameAndVersionPair($input);

        $downloadedPackage = CommandHelper::downloadPackage(
            $this->dependencyResolver,
            $targetPlatform,
            $requestedNameAndVersionPair,
            $this->downloadAndExtract,
            $output,
        );

        CommandHelper::bindConfigureOptionsFromPackage($this, $downloadedPackage->package, $input);

        $configureOptionsValues = CommandHelper::processConfigureOptionsFromInput($downloadedPackage->package, $input);

        ($this->build)($downloadedPackage, $targetPlatform, $configureOptionsValues, $output);

        ($this->install)($downloadedPackage, $targetPlatform, $output);

        return Command::SUCCESS;
    }
}
