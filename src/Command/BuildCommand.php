<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Php\Pie\Building\Build;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\Downloading\DownloadAndExtract;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'build',
    description: 'Download and build a PIE-compatible PHP extension, without installing it.',
)]
final class BuildCommand extends Command
{
    public function __construct(
        private readonly DependencyResolver $dependencyResolver,
        private readonly DownloadAndExtract $downloadAndExtract,
        private readonly Build $build,
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
        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $output);

        $requestedNameAndVersionPair = CommandHelper::requestedNameAndVersionPair($input);

        $downloadedPackage = CommandHelper::downloadPackage(
            $this->dependencyResolver,
            $targetPlatform,
            $requestedNameAndVersionPair,
            $this->downloadAndExtract,
            $output,
        );

        ($this->build)($downloadedPackage, $output);

        return Command::SUCCESS;
    }
}
