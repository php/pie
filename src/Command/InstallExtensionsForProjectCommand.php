<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\Link;
use Composer\Package\Version\VersionParser;
use OutOfRangeException;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieJsonEditor;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\InstallForPhpProject\ComposerFactoryForProject;
use Php\Pie\Installing\InstallForPhpProject\DetermineExtensionsRequired;
use Php\Pie\Installing\InstallForPhpProject\FindMatchingPackages;
use Php\Pie\Installing\InstallForPhpProject\InstallPiePackageFromPath;
use Php\Pie\Installing\InstallForPhpProject\InstallSelectedPackage;
use Php\Pie\Platform;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Util\Emoji;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_column;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_walk;
use function assert;
use function chdir;
use function count;
use function getcwd;
use function implode;
use function in_array;
use function is_dir;
use function is_string;
use function realpath;
use function sprintf;
use function strtolower;

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
        private readonly FindMatchingPackages $findMatchingPackages,
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

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDirOption  = (string) $input->getOption(CommandHelper::OPTION_WORKING_DIRECTORY);
        $restoreWorkingDir = static function (): void {
        };
        if ($workingDirOption !== '' && is_dir($workingDirOption)) {
            $currentWorkingDir = getcwd();
            $restoreWorkingDir = function () use ($currentWorkingDir): void {
                chdir($currentWorkingDir);
                $this->io->write(
                    sprintf('Restored working directory to: %s', $currentWorkingDir),
                    verbosity: IOInterface::VERBOSE,
                );
            };

            chdir($workingDirOption);
            $this->io->write(
                sprintf('Changed working directory to: %s', $workingDirOption),
                verbosity: IOInterface::VERBOSE,
            );
        }

        CommandHelper::applyNoCacheOptionIfSet($input, $this->io);

        $rootPackage = $this->composerFactoryForProject->rootPackage($this->io);

        if (ExtensionType::isValid($rootPackage->getType())) {
            $cwd = realpath(getcwd());
            if (! is_string($cwd) || $cwd === '') {
                $this->io->writeError('<error>Failed to determine current working directory.</error>');

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

        $allowNonInteractive = $input->hasOption(CommandHelper::OPTION_ALLOW_NON_INTERACTIVE_PROJECT_INSTALL) && $input->getOption(CommandHelper::OPTION_ALLOW_NON_INTERACTIVE_PROJECT_INSTALL);
        if (! Platform::isInteractive() && ! $allowNonInteractive) {
            $this->io->writeError(sprintf(
                '<warning>Aborting! You are not running in interactive mode, and --%s was not specified.</warning>',
                CommandHelper::OPTION_ALLOW_NON_INTERACTIVE_PROJECT_INSTALL,
            ));

            return Command::FAILURE;
        }

        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $this->io);

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

        array_walk(
            $extensionsRequired,
            function (Link $link) use ($pieComposer, $phpEnabledExtensions, $installedPiePackages, $input, &$anyErrorsHappened): void {
                $extension              = ExtensionName::normaliseFromString($link->getTarget());
                $linkRequiresConstraint = $link->getPrettyConstraint();

                $piePackageVersion = null;
                if (in_array($extension->name(), array_keys($installedPiePackages))) {
                    $piePackageVersion = $installedPiePackages[$extension->name()]->version();
                }

                $piePackageVersionMatchesLinkConstraint = null;
                if ($piePackageVersion !== null) {
                    $piePackageVersionMatchesLinkConstraint = $link
                        ->getConstraint()
                        ->matches(
                            (new VersionParser())->parseConstraints($piePackageVersion),
                        );
                }

                if (in_array(strtolower($extension->name()), $phpEnabledExtensions)) {
                    if ($piePackageVersion !== null && $piePackageVersionMatchesLinkConstraint === false) {
                        $this->io->write(sprintf(
                            '%s: <comment>%s:%s</comment> %s Version %s is installed, but does not meet the version requirement %s',
                            $link->getDescription(),
                            $link->getTarget(),
                            $linkRequiresConstraint,
                            Emoji::WARNING,
                            $piePackageVersion,
                            $link->getConstraint()->getPrettyString(),
                        ));

                        return;
                    }

                    $this->io->write(sprintf(
                        '%s: <info>%s:%s</info> %s Already installed',
                        $link->getDescription(),
                        $link->getTarget(),
                        $linkRequiresConstraint,
                        Emoji::GREEN_CHECKMARK,
                    ));

                    return;
                }

                $this->io->write(sprintf(
                    '%s: <comment>%s:%s</comment> %s Missing',
                    $link->getDescription(),
                    $link->getTarget(),
                    $linkRequiresConstraint,
                    Emoji::PROHIBITED,
                ));

                try {
                    $matches = $this->findMatchingPackages->for($pieComposer, $extension->name());
                } catch (OutOfRangeException) {
                    $anyErrorsHappened = true;

                    $this->io->writeError(sprintf(
                        '<error>No packages were found for %s</error>',
                        $extension->nameWithExtPrefix(),
                    ));

                    return;
                }

                if (! Platform::isInteractive() && count($matches) > 1) {
                    $anyErrorsHappened = true;

                    // @todo Figure out if there is a way to improve this, safely
                    $this->io->writeError(sprintf(
                        "<warning>Multiple packages were found for %s:</warning>\n  %s\n\n<warning>This means you cannot `pie install` this project interactively for now.</warning>",
                        $extension->nameWithExtPrefix(),
                        implode("\n  ", array_column($matches, 'name')),
                    ));

                    return;
                }

                if (Platform::isInteractive()) {
                    $selectedPackageAnswer = (int) $this->io->select(
                        "\nThe following packages may be suitable, which would you like to install: ",
                        array_merge(
                            ['None'],
                            array_map(
                                static function (array $match): string {
                                    return sprintf('%s: %s', $match['name'], $match['description'] ?? 'no description available');
                                },
                                $matches,
                            ),
                        ),
                        '0',
                    );

                    if ($selectedPackageAnswer === 0) {
                        $this->io->write('Okay I won\'t install anything for ' . $extension->name());
                        $anyErrorsHappened = true;

                        return;
                    }

                    $matchesKey = $selectedPackageAnswer - 1;
                    assert(array_key_exists($matchesKey, $matches));

                    $selectedPackageName = $matches[$matchesKey]['name'];
                } else {
                    $selectedPackageName = $matches[0]['name'];
                }

                $requestInstallConstraint = '';
                if ($linkRequiresConstraint !== '*') {
                    $requestInstallConstraint = ':' . $linkRequiresConstraint;
                }

                try {
                    $this->io->write(
                        sprintf('Invoking pie install of %s%s', $selectedPackageName, $requestInstallConstraint),
                        verbosity: IOInterface::VERBOSE,
                    );
                    $this->installSelectedPackage->withPieCli(
                        $selectedPackageName . $requestInstallConstraint,
                        $input,
                        $this->io,
                    );
                } catch (Throwable $t) {
                    $anyErrorsHappened = true;

                    $this->io->writeError('<error>' . $t->getMessage() . '</error>');
                }
            },
        );

        $this->io->write(PHP_EOL . 'Finished checking extensions.');

        $restoreWorkingDir();

        return $anyErrorsHappened ? self::FAILURE : self::SUCCESS;
    }
}
