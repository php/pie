<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\DependencyResolver;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function sprintf;

#[AsCommand(
    name: 'info',
    description: 'Show metadata about a given extension.',
)]
final class InfoCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DependencyResolver $dependencyResolver,
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

        $requestedNameAndVersion = CommandHelper::requestedNameAndVersionPair($input);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $output,
                $targetPlatform,
                $requestedNameAndVersion,
                PieOperation::Resolve,
                [], // Configure options are not needed for resolve only
            ),
        );

        $package = ($this->dependencyResolver)($composer, $targetPlatform, $requestedNameAndVersion);
        $output->writeln(sprintf('<info>Found package:</info> %s which provides <info>%s</info>', $package->prettyNameAndVersion(), $package->extensionName->nameWithExtPrefix()));

        $output->writeln(sprintf('Extension name: %s', $package->extensionName->name()));
        $output->writeln(sprintf('Extension type: %s (%s)', $package->extensionType->value, $package->extensionType->name));
        $output->writeln(sprintf('Composer package name: %s', $package->name));
        $output->writeln(sprintf('Version: %s', $package->version));
        $output->writeln(sprintf('Download URL: %s', $package->downloadUrl ?? '(not specified)'));

        if (count($package->configureOptions)) {
            $output->writeln('Configure options:');
            foreach ($package->configureOptions as $configureOption) {
                $output->writeln(sprintf('    --%s%s  (%s)', $configureOption->name, $configureOption->needsValue ? '=?' : '', $configureOption->description));
            }
        } else {
            $output->writeln('No configure options are specified.');
        }

        return Command::SUCCESS;
    }
}
