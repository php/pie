<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\IO\IOInterface;
use Php\Pie\ComposerIntegration\ComposerIntegrationHandler;
use Php\Pie\ComposerIntegration\ComposerRunFailed;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\BundledPhpExtensionRefusal;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\InvalidPackageName;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use Php\Pie\Installing\InstallForPhpProject\FindMatchingPackages;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\SelfManage\BuildTools\CheckAllBuildTools;
use Php\Pie\SelfManage\BuildTools\PackageManager;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
        private readonly FindMatchingPackages $findMatchingPackages,
        private readonly IOInterface $io,
        private readonly CheckAllBuildTools $checkBuildTools,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        $this->addArgument(
            CommandHelper::ARG_REQUESTED_PACKAGE_AND_VERSION,
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'The PIE package name(s) and version constraint(s) to install. Can specify multiple packages separated by space, e.g., xdebug/xdebug redis/redis:^5.0',
        );

        CommandHelper::configureDownloadBuildInstallOptions($this, false);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $requestedPackages = $input->getArgument(CommandHelper::ARG_REQUESTED_PACKAGE_AND_VERSION);
        if (! is_array($requestedPackages) || count($requestedPackages) === 0) {
            return ($this->invokeSubCommand)(
                $this,
                ['command' => 'install-extensions-for-project'],
                $input,
            );
        }

        if (! TargetPlatform::isRunningAsRoot()) {
            $this->io->write('This command may need elevated privileges, and may prompt you for your password.');
        }

        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $this->io);

        // 解析所有待安装的包
        try {
            $requestedPackagesList = CommandHelper::requestedNameAndVersionPairs($input);
        } catch (InvalidPackageName $invalidPackageName) {
            return CommandHelper::handlePackageNotFound(
                $invalidPackageName,
                $this->findMatchingPackages,
                $this->io,
                $targetPlatform,
                $this->container,
            );
        }

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

        $totalPackages = count($requestedPackagesList);

        // 如果只有一个包，使用原有的详细输出模式
        if ($totalPackages === 1) {
            return $this->installSinglePackage(
                $requestedPackagesList[0],
                $targetPlatform,
                $forceInstallPackageVersion,
                $input,
                true, // verbose mode
            );
        }

        // 多包安装模式
        $this->io->write(sprintf(
            '<info>Installing %d extensions...</info>',
            $totalPackages,
        ));
        $this->io->write('');

        $successCount   = 0;
        $failedPackages = [];

        foreach ($requestedPackagesList as $index => $requestedNameAndVersion) {
            $packageNumber = $index + 1;
            $this->io->write(sprintf(
                '<comment>[%d/%d]</comment> Processing <info>%s</info>...',
                $packageNumber,
                $totalPackages,
                $requestedNameAndVersion->package,
            ));

            try {
                $installResult = $this->installSinglePackage(
                    $requestedNameAndVersion,
                    $targetPlatform,
                    $forceInstallPackageVersion,
                    $input,
                    false, // quiet mode
                );

                if ($installResult === Command::SUCCESS) {
                    $successCount++;
                    $this->io->write(sprintf(
                        '<info>✓ Successfully installed %s</info>',
                        $requestedNameAndVersion->package,
                    ));
                } else {
                    $failedPackages[] = $requestedNameAndVersion->package;
                    $this->io->writeError(sprintf(
                        '<error>✗ Failed to install %s</error>',
                        $requestedNameAndVersion->package,
                    ));
                }
            } catch (UnableToResolveRequirement $unableToResolveRequirement) {
                $failedPackages[] = $requestedNameAndVersion->package;
                $this->io->writeError(sprintf(
                    '<error>✗ Could not resolve %s: %s</error>',
                    $requestedNameAndVersion->package,
                    $unableToResolveRequirement->getMessage(),
                ));
            } catch (BundledPhpExtensionRefusal $bundledPhpExtensionRefusal) {
                $failedPackages[] = $requestedNameAndVersion->package;
                $this->io->writeError(sprintf(
                    '<comment>✗ Skipped %s: %s</comment>',
                    $requestedNameAndVersion->package,
                    $bundledPhpExtensionRefusal->getMessage(),
                ));
            } catch (ComposerRunFailed $composerRunFailed) {
                $failedPackages[] = $requestedNameAndVersion->package;
                $this->io->writeError(sprintf(
                    '<error>✗ Installation failed for %s: %s</error>',
                    $requestedNameAndVersion->package,
                    $composerRunFailed->getMessage(),
                ));
            }

            $this->io->write('');
        }

        // 输出总结
        $this->io->write('<info>=====================================</info>');
        $this->io->write(sprintf(
            '<info>Installation Summary:</info> %d succeeded, %d failed out of %d total',
            $successCount,
            count($failedPackages),
            $totalPackages,
        ));

        if (count($failedPackages) > 0) {
            $this->io->write('<error>Failed packages:</error>');
            foreach ($failedPackages as $failedPackage) {
                $this->io->write(sprintf('  - %s', $failedPackage));
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * 安装单个扩展包
     *
     * @throws UnableToResolveRequirement
     * @throws BundledPhpExtensionRefusal
     * @throws ComposerRunFailed
     * @throws InvalidPackageName
     */
    private function installSinglePackage(
        RequestedPackageAndVersion $requestedNameAndVersion,
        TargetPlatform $targetPlatform,
        bool $forceInstallPackageVersion,
        InputInterface $input,
        bool $verboseOutput = true,
    ): int {
        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $this->io,
                $targetPlatform,
                $requestedNameAndVersion,
                PieOperation::Resolve,
                [],
                null,
                false,
            ),
        );

        $package = ($this->dependencyResolver)(
            $composer,
            $targetPlatform,
            $requestedNameAndVersion,
            $forceInstallPackageVersion,
        );

        if ($verboseOutput) {
            $this->io->write(sprintf(
                '<info>Found package:</info> %s which provides <info>%s</info>',
                $package->prettyNameAndVersion(),
                $package->extensionName()->nameWithExtPrefix(),
            ));
        } else {
            $this->io->write(
                sprintf(
                    '  Found package: %s which provides %s',
                    $package->prettyNameAndVersion(),
                    $package->extensionName()->nameWithExtPrefix(),
                ),
                verbosity: IOInterface::VERBOSE,
            );
        }

        // 验证配置选项
        CommandHelper::bindConfigureOptionsFromPackage($this, $package, $input);
        $configureOptionsValues = CommandHelper::processConfigureOptionsFromInput($package, $input);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $this->io,
                $targetPlatform,
                $requestedNameAndVersion,
                PieOperation::Install,
                $configureOptionsValues,
                CommandHelper::determinePhpizePathFromInputs($input),
                CommandHelper::determineAttemptToSetupIniFile($input),
            ),
        );

        $this->composerIntegrationHandler->runInstall(
            $package,
            $composer,
            $targetPlatform,
            $requestedNameAndVersion,
            $forceInstallPackageVersion,
            true,
        );

        return Command::SUCCESS;
    }
}
