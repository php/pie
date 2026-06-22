<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use OutOfRangeException;
use Php\Pie\ComposerIntegration\ComposerIntegrationHandler;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieOperation;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\DependencyResolver\ResolvedPackageRequest;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\TargetPlatform;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function array_map;

#[AsCommand(
    name: 'uninstall',
    description: 'Disable and remove an extension that has been installed with PIE',
)]
final class UninstallCommand extends Command
{
    private const ARG_PACKAGE_NAMES = 'package-name';

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
            self::ARG_PACKAGE_NAMES,
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'The package names to remove, in the format {vendor/package}, for example `xdebug/xdebug`',
        );

        CommandHelper::configurePhpConfigOptions($this);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! TargetPlatform::isRunningAsRoot()) {
            $this->io->write('This command may need elevated privileges, and may prompt you for your password.');
        }

        $packagesToRemove = $input->getArgument(self::ARG_PACKAGE_NAMES);
        Assert::isList($packagesToRemove);
        Assert::allStringNotEmpty($packagesToRemove);
        $requestedPackageAndVersionsToRemove = array_map(
            static fn (string $packageName) => new RequestedPackageAndVersion($packageName, null),
            $packagesToRemove,
        );

        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $this->io);

        CommandHelper::applyNoCacheOptionIfSet($input, $this->io);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            PieComposerRequest::noOperation(
                new NullIO(),
                $targetPlatform,
            ),
        );

        $piePackages = $this->installedPiePackages->allPiePackages($composer);

        try {
            $resolvedPackages = array_map(
                static fn (RequestedPackageAndVersion $req) => new ResolvedPackageRequest(
                    $piePackages->findByPackageName($req->package),
                    $req,
                ),
                $requestedPackageAndVersionsToRemove,
            );
        } catch (OutOfRangeException $exception) {
            $this->io->writeError('<error>No package found: ' . $exception->getMessage() . '</error>');

            return 1;
        }

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            new PieComposerRequest(
                $this->io,
                $targetPlatform,
                $requestedPackageAndVersionsToRemove,
                PieOperation::Uninstall,
                [], // Configure options are not needed for uninstall
                true,
            ),
        );

        $this->composerIntegrationHandler->runUninstall(
            $resolvedPackages,
            $composer,
            $targetPlatform,
        );

        return 0;
    }
}
