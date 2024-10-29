<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Php\Pie\ComposerIntegration\ComposerIntegrationHandler;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\DependencyResolver;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

#[AsCommand(
    name: 'build',
    description: 'Download and build a PIE-compatible PHP extension, without installing it.',
)]
final class BuildCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DependencyResolver $dependencyResolver,
        private readonly ComposerIntegrationHandler $composerIntegrationHandler,
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
        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $output);

        $requestedNameAndVersion = CommandHelper::requestedNameAndVersionPair($input);

        $composer = CommandHelper::createComposer(
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

        // Now we know what package we have, we can validate the configure options for the command and re-create the
        // Composer instance with the populated configure options
        CommandHelper::bindConfigureOptionsFromPackage($this, $package, $input);
        $configureOptionsValues = CommandHelper::processConfigureOptionsFromInput($package, $input);

        $composer = CommandHelper::createComposer(
            $this->container,
            new PieComposerRequest(
                $output,
                $targetPlatform,
                $requestedNameAndVersion,
                PieOperation::Build,
                $configureOptionsValues,
            ),
        );

        ($this->composerIntegrationHandler)($package, $composer, $targetPlatform, $requestedNameAndVersion);

        return Command::SUCCESS;
    }
}
