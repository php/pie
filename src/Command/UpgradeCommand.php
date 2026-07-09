<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\IO\IOInterface;
use Php\Pie\ComposerIntegration\ComposerIntegrationHandler;
use Php\Pie\ComposerIntegration\ComposerRunFailed;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\Platform;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\PackageManager;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\SelfManage\BuildTools\CheckAllBuildTools;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function explode;
use function file_exists;

#[AsCommand(
    name: 'upgrade',
    description: 'Upgrade all PIE-installed extensions to their latest allowed versions.',
)]
final class UpgradeCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ComposerIntegrationHandler $composerIntegrationHandler,
        private readonly IOInterface $io,
        private readonly CheckAllBuildTools $checkBuildTools,
        private readonly InstalledPiePackages $installedPiePackages,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        CommandHelper::configureDownloadBuildInstallOptions($this, false);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! TargetPlatform::isRunningAsRoot()) {
            $this->io->write('This command may need elevated privileges, and may prompt you for your password.');
        }

        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $this->io);

        $forceInstallPackageVersion = CommandHelper::determineForceInstallingPackageVersion($input);
        CommandHelper::applyNoCacheOptionIfSet($input, $this->io);

        if (CommandHelper::shouldCheckForBuildTools($input)) {
            $this->checkBuildTools->check(
                $this->io,
                PackageManager::detect(),
                $targetPlatform,
                CommandHelper::autoInstallBuildTools($input),
            );
        }

        if (! file_exists(PieComposerFactory::getLockFile(Platform::getPieJsonFilename($targetPlatform)))) {
            $this->io->writeError('<error>No PIE extensions are currently installed, so there is nothing to upgrade.</error>');

            return Command::INVALID;
        }

        $existingComposer = PieComposerFactory::createPieComposer(
            $this->container,
            PieComposerRequest::noOperation($this->io, $targetPlatform),
        );

        $configureOptions = [];
        foreach ($this->installedPiePackages->allPiePackages($existingComposer)->packages() as $installedPackage) {
            $existingConfigureOptions = $installedPackage->installedJsonMetadata()->configureOptions();
            if ($existingConfigureOptions === null) {
                continue;
            }

            $existingConfigureOptionsList = explode(' ', $existingConfigureOptions);
            Assert::allStringNotEmpty($existingConfigureOptionsList);

            $configureOptions[$installedPackage->name()] = $existingConfigureOptionsList;
        }

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $this->io,
                $targetPlatform,
                [],
                PieOperation::Install,
                $configureOptions,
                CommandHelper::determineAttemptToSetupIniFile($input),
                installAllPackages: true,
            ),
        );

        try {
            $this->composerIntegrationHandler->runInstall(
                [],
                $composer,
                $targetPlatform,
                $forceInstallPackageVersion,
                true,
            );
        } catch (ComposerRunFailed $composerRunFailed) {
            $this->io->writeError('<error>' . $composerRunFailed->getMessage() . '</error>');

            return $composerRunFailed->getCode();
        }

        return Command::SUCCESS;
    }
}
