<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Php\Pie\ComposerIntegration\ComposerIntegrationHandler;
use Php\Pie\ComposerIntegration\ComposerRunFailed;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\Platform\TargetPlatform;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

#[AsCommand(
    name: 'install',
    description: 'Download, build, and install a PIE-compatible PHP extension.',
)]
final class InstallCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DependencyResolver $dependencyResolver,
        private readonly ComposerIntegrationHandler $composerIntegrationHandler,
        private readonly InvokeSubCommand $invokeSubCommand,
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
        if (! $input->getArgument(CommandHelper::ARG_REQUESTED_PACKAGE_AND_VERSION)) {
            return ($this->invokeSubCommand)(
                $this,
                ['command' => 'install-extensions-for-project'],
                $input,
                $output,
            );
        }

        if (! TargetPlatform::isRunningAsRoot()) {
            $output->writeln('This command may need elevated privileges, and may prompt you for your password.');
        }

        $targetPlatform             = CommandHelper::determineTargetPlatformFromInputs($input, $output);
        $requestedNameAndVersion    = CommandHelper::requestedNameAndVersionPair($input);
        $forceInstallPackageVersion = CommandHelper::determineForceInstallingPackageVersion($input);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $output,
                $targetPlatform,
                $requestedNameAndVersion,
                PieOperation::Resolve,
                [], // Configure options are not needed for resolve only
                null,
                false, // setting up INI not needed for resolve step
            ),
        );

        $package = ($this->dependencyResolver)(
            $composer,
            $targetPlatform,
            $requestedNameAndVersion,
            $forceInstallPackageVersion,
        );
        $output->writeln(sprintf('<info>Found package:</info> %s which provides <info>%s</info>', $package->prettyNameAndVersion(), $package->extensionName()->nameWithExtPrefix()));

        // Now we know what package we have, we can validate the configure options for the command and re-create the
        // Composer instance with the populated configure options
        CommandHelper::bindConfigureOptionsFromPackage($this, $package, $input);
        $configureOptionsValues = CommandHelper::processConfigureOptionsFromInput($package, $input);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $output,
                $targetPlatform,
                $requestedNameAndVersion,
                PieOperation::Install,
                $configureOptionsValues,
                CommandHelper::determinePhpizePathFromInputs($input),
                CommandHelper::determineAttemptToSetupIniFile($input),
            ),
        );

        try {
            $this->composerIntegrationHandler->runInstall(
                $package,
                $composer,
                $targetPlatform,
                $requestedNameAndVersion,
                $forceInstallPackageVersion,
            );
        } catch (ComposerRunFailed $composerRunFailed) {
            $output->writeln('<error>' . $composerRunFailed->getMessage() . '</error>');

            return $composerRunFailed->getCode();
        }

        return Command::SUCCESS;
    }
}
