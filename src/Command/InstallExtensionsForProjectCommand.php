<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieJsonEditor;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\InstallForPhpProject\CheckExtensionStatus;
use Php\Pie\Installing\InstallForPhpProject\ComposerFactoryForProject;
use Php\Pie\Installing\InstallForPhpProject\DetermineExtensionsRequired;
use Php\Pie\Installing\InstallForPhpProject\InstallPiePackageFromPath;
use Php\Pie\Installing\InstallForPhpProject\InstallSelectedPackage;
use Php\Pie\Installing\InstallForPhpProject\NoMatchingPackagesFound;
use Php\Pie\Installing\InstallForPhpProject\PackageSelectionRequired;
use Php\Pie\Installing\InstallForPhpProject\SelectPackageForExtension;
use Php\Pie\Platform;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\PiePackageList;
use Php\Pie\Platform\TargetPlatform;
use Psr\Container\ContainerInterface;
use Safe\Exceptions\DirException;
use Safe\Exceptions\FilesystemException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Webmozart\Assert\Assert;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function implode;
use function Safe\getcwd;
use function Safe\realpath;
use function sprintf;

use const PHP_EOL;

#[AsCommand(
    name: 'install-extensions-for-project',
    description: 'Check a project for its extension dependencies, and offers to install them',
)]
final class InstallExtensionsForProjectCommand extends Command
{
    public function __construct(
        private readonly ComposerFactoryForProject $composerFactoryForProject,
        private readonly DetermineExtensionsRequired $determineExtensionsRequired,
        private readonly InstalledPiePackages $installedPiePackages,
        private readonly CheckExtensionStatus $checkExtensionStatus,
        private readonly SelectPackageForExtension $selectPackageForExtension,
        private readonly InstallSelectedPackage $installSelectedPackage,
        private readonly InstallPiePackageFromPath $installPiePackageFromPath,
        private readonly ContainerInterface $container,
        private readonly IOInterface $io,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        CommandHelper::configureDownloadBuildInstallOptions($this, false);
    }

    private function handlePieProject(InputInterface $input, RootPackageInterface $rootPackage, callable $restoreWorkingDir): int
    {
        try {
            $cwd = realpath(getcwd());
        } catch (FilesystemException | DirException $e) {
            $this->io->writeError(sprintf(
                '<error>Failed to determine current working directory: %s</error>',
                $e->getMessage(),
            ));

            $restoreWorkingDir();

            return Command::FAILURE;
        }

        $exit = ($this->installPiePackageFromPath)(
            $this,
            $cwd,
            $rootPackage,
            PieJsonEditor::fromTargetPlatform(CommandHelper::determineTargetPlatformFromInputs($input, new NullIO())),
            $input,
            $this->io,
        );

        $restoreWorkingDir();

        return $exit;
    }

    private function handlePhpProject(InputInterface $input, RootPackageInterface $rootPackage, callable $restoreWorkingDir): int
    {
        $extensionToPackageSelections = CommandHelper::determineExtensionToPackageSelections($input);
        $targetPlatform               = CommandHelper::determineTargetPlatformFromInputs($input, $this->io);

        $allowNonInteractive = $input->hasOption(CommandHelper::OPTION_ALLOW_NON_INTERACTIVE_PROJECT_INSTALL) && $input->getOption(CommandHelper::OPTION_ALLOW_NON_INTERACTIVE_PROJECT_INSTALL);
        if ($allowNonInteractive) {
            $this->io->writeError(sprintf(
                '<warning>The --%s is now deprecated and has no effect.</warning>',
                CommandHelper::OPTION_ALLOW_NON_INTERACTIVE_PROJECT_INSTALL,
            ));
        }

        $this->io->write(sprintf(
            'Checking extensions for your project <info>%s</info> (path: %s)',
            $rootPackage->getPrettyName(),
            getcwd(),
        ));

        $extensionsRequired = $this->determineExtensionsRequired->forProject($this->composerFactoryForProject->composer($this->io));

        $pieComposer = PieComposerFactory::createPieComposer(
            $this->container,
            PieComposerRequest::noOperation(
                new NullIO(),
                $targetPlatform,
            ),
        );

        $phpEnabledExtensions = array_map('strtolower', array_keys($targetPlatform->phpBinaryPath->extensions()));
        $installedPiePackages = $this->installedPiePackages->allPiePackages($pieComposer);

        $anyErrorsHappened = false;

        $scheduledForInstall = array_values(array_filter(array_map(
            function (Link $link) use ($pieComposer, $phpEnabledExtensions, $installedPiePackages, &$anyErrorsHappened, $targetPlatform, $extensionToPackageSelections): RequestedPackageAndVersion|null {
                $result = $this->handleSingleExtensionRequiredByPhpProject(
                    $pieComposer,
                    $link,
                    $installedPiePackages,
                    $targetPlatform,
                    $phpEnabledExtensions,
                    $extensionToPackageSelections,
                );

                if ($result instanceof RequestedPackageAndVersion) {
                    return $result;
                }

                if ($result === false) {
                    $anyErrorsHappened = true;
                }

                return null;
            },
            $extensionsRequired,
        )));

        if (count($scheduledForInstall)) {
            $nicePackageList = implode(', ', array_map(
                static fn (RequestedPackageAndVersion $req) => $req->prettyNameAndVersion(),
                $scheduledForInstall,
            ));

            try {
                $this->io->write(
                    sprintf('Invoking pie install of %s', $nicePackageList),
                    verbosity: IOInterface::VERBOSE,
                );
                Assert::same(
                    0,
                    $this->installSelectedPackage->withSubCommand(
                        $scheduledForInstall,
                        $this,
                        $input,
                    ),
                    'Non-zero exit code %s whilst installing ' . $nicePackageList,
                );
            } catch (Throwable $t) {
                $this->io->writeError('<error>' . $t->getMessage() . '</error>');

                $anyErrorsHappened = true;
            }
        }

        $this->io->write(PHP_EOL . 'Finished checking extensions.');

        $restoreWorkingDir();

        return $anyErrorsHappened ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Returns false if any error happened whilst trying to install the extension; returns true if was already
     * installed, or it was successfully installed if needed.
     *
     * @param list<string>                              $phpEnabledExtensions
     * @param array<non-empty-string, non-empty-string> $extensionToPackageSelections
     */
    private function handleSingleExtensionRequiredByPhpProject(
        Composer $pieComposer,
        Link $link,
        PiePackageList $installedPiePackages,
        TargetPlatform $targetPlatform,
        array $phpEnabledExtensions,
        array $extensionToPackageSelections,
    ): RequestedPackageAndVersion|bool {
        $extension               = ExtensionName::normaliseFromString($link->getTarget());
        $piePackagesForExtension = $installedPiePackages
            ->findByPhpFormattedExtensionName($extension->phpFormattedExtensionName())
            ->onlyVerifiedFor($targetPlatform);

        // Check if the extension is already installed, if it is, return early.
        if (($this->checkExtensionStatus)($link, $piePackagesForExtension, $phpEnabledExtensions)) {
            return true;
        }

        try {
            $requestedPackageAndVersion = ($this->selectPackageForExtension)(
                $extension,
                $link->getPrettyConstraint(),
                $extensionToPackageSelections,
                $pieComposer,
                Platform::isInteractive(),
            );
        } catch (NoMatchingPackagesFound $e) {
            $this->io->write($e->getMessage());

            return false;
        } catch (PackageSelectionRequired $e) {
            // @todo https://github.com/php/pie/issues/592
            $options = array_map(
                static fn (array $match) => sprintf('  --select=%s=%s', $e->extensionName->name(), $match['name']),
                $e->matches,
            );

            $this->io->writeError(sprintf(
                '<warning>No package selections were made for %s; you MUST specify a package selection in non-interactive mode, by adding one of the following parameters to the `pie install` command:</warning>%s',
                $e->extensionName->nameWithExtPrefix(),
                "\n" . implode("\n", $options),
            ));

            return false;
        }

        // Interactive user did not select a package to install
        if ($requestedPackageAndVersion === null) {
            return false;
        }

        return $requestedPackageAndVersion;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $restoreWorkingDir = CommandHelper::handleWorkingDirectory($input, $this->io);
        CommandHelper::applyNoCacheOptionIfSet($input, $this->io);

        $rootPackage = $this->composerFactoryForProject->rootPackage($this->io);

        if (ExtensionType::isValid($rootPackage->getType())) {
            return $this->handlePieProject($input, $rootPackage, $restoreWorkingDir);
        }

        return $this->handlePhpProject($input, $rootPackage, $restoreWorkingDir);
    }
}
