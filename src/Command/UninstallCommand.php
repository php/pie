<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Php\Pie\ComposerIntegration\ComposerIntegrationHandler;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\TargetPlatform;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'uninstall',
    description: 'Disable and remove an extension that has been installed with PIE',
)]
final class UninstallCommand extends Command
{
    private const ARG_PACKAGE_NAME = 'package-name';

    public function __construct(
        private readonly InstalledPiePackages $installedPiePackages,
        private readonly ContainerInterface $container,
        private readonly ComposerIntegrationHandler $composerIntegrationHandler,
        private readonly IOInterface $io,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        $this->addArgument(
            self::ARG_PACKAGE_NAME,
            InputArgument::REQUIRED,
            'The package name to remove, in the format {vendor/package}, for example `xdebug/xdebug`',
        );

        CommandHelper::configurePhpConfigOptions($this);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! TargetPlatform::isRunningAsRoot()) {
            $this->io->write('This command may need elevated privileges, and may prompt you for your password.');
        }

        $packageToRemove = (string) $input->getArgument(self::ARG_PACKAGE_NAME);
        Assert::stringNotEmpty($packageToRemove);
        $requestedPackageAndVersionToRemove = new RequestedPackageAndVersion($packageToRemove, null);

        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $this->io);

        CommandHelper::applyNoCacheOptionIfSet($input, $this->io);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            PieComposerRequest::noOperation(
                new NullIO(),
                $targetPlatform,
            ),
        );

        $piePackage = $this->findPiePackageByPackageName($packageToRemove, $composer);

        if ($piePackage === null) {
            $this->io->writeError('<error>No package found: ' . $packageToRemove . '</error>');

            return 1;
        }

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $this->io,
                $targetPlatform,
                $requestedPackageAndVersionToRemove,
                PieOperation::Uninstall,
                [], // Configure options are not needed for uninstall
                null,
                true,
            ),
        );

        $this->composerIntegrationHandler->runUninstall(
            $piePackage,
            $composer,
            $targetPlatform,
            $requestedPackageAndVersionToRemove,
        );

        return 0;
    }

    private function findPiePackageByPackageName(string $packageToRemove, Composer $composer): Package|null
    {
        $piePackages = $this->installedPiePackages->allPiePackages($composer);

        foreach ($piePackages as $piePackage) {
            if ($piePackage->name() === $packageToRemove) {
                return $piePackage;
            }
        }

        return null;
    }
}
