<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\Downloading\DownloadAndExtract;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

#[AsCommand(
    name: 'download',
    description: 'Same behaviour as build, but puts the files in a local directory for manual building and installation.',
)]
final class DownloadCommand extends Command
{
    public function __construct(
        private readonly DependencyResolver $dependencyResolver,
        private readonly DownloadAndExtract $downloadAndExtract,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        CommandHelper::configureDownloadBuildInstallOptions($this);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        CommandHelper::validateInput($input, $this);

        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $output);

        $requestedNameAndVersionPair = CommandHelper::requestedNameAndVersionPair($input);

        $downloadedPackage = CommandHelper::downloadPackage(
            $this->dependencyResolver,
            $targetPlatform,
            $requestedNameAndVersionPair,
            $this->downloadAndExtract,
            $output,
        );

        $output->writeln(sprintf(
            '<info>Extracted %s source to:</info> %s',
            $downloadedPackage->package->prettyNameAndVersion(),
            $downloadedPackage->extractedSourcePath,
        ));

        return Command::SUCCESS;
    }
}
