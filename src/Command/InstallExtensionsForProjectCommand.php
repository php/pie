<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\Package\Link;
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
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Throwable;

use function array_keys;
use function array_map;
use function array_merge;
use function array_walk;
use function assert;
use function chdir;
use function getcwd;
use function in_array;
use function is_dir;
use function is_string;
use function realpath;
use function sprintf;
use function strpos;
use function substr;

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
        private readonly FindMatchingPackages $findMatchingPackages,
        private readonly InstallSelectedPackage $installSelectedPackage,
        private readonly InstallPiePackageFromPath $installPiePackageFromPath,
        private readonly ContainerInterface $container,
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
        $helper = $this->getHelper('question');
        assert($helper instanceof QuestionHelper);

        $workingDirOption = (string) $input->getOption(CommandHelper::OPTION_WORKING_DIRECTORY);
        if ($workingDirOption !== '' && is_dir($workingDirOption)) {
            chdir($workingDirOption);
            $output->writeln(
                sprintf('Changed working directory to: %s', $workingDirOption),
                OutputInterface::VERBOSITY_VERBOSE,
            );
        }

        // @todo check if we need to revert the cwd on exit (would need to check all exit branches)

        $rootPackage = $this->composerFactoryForProject->rootPackage($input, $output);

        if (ExtensionType::isValid($rootPackage->getType())) {
            $cwd = realpath(getcwd());
            if (! is_string($cwd) || $cwd === '') {
                $output->writeln('<error>Failed to determine current working directory.</error>');

                return Command::FAILURE;
            }

            return ($this->installPiePackageFromPath)(
                $this,
                $cwd,
                $rootPackage,
                PieJsonEditor::fromTargetPlatform(CommandHelper::determineTargetPlatformFromInputs($input, new NullOutput())),
                $input,
                $output,
            );
        }

        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $output);

        $output->writeln(sprintf(
            'Checking extensions for your project <info>%s</info> (path: %s)',
            $rootPackage->getPrettyName(),
            getcwd(),
        ));

        $extensionsRequired = $this->determineExtensionsRequired->forProject($this->composerFactoryForProject->composer($input, $output));

        $pieComposer = PieComposerFactory::createPieComposer(
            $this->container,
            PieComposerRequest::noOperation(
                new NullOutput(),
                $targetPlatform,
            ),
        );

        $phpEnabledExtensions = array_keys($targetPlatform->phpBinaryPath->extensions());

        $anyErrorsHappened = false;

        array_walk(
            $extensionsRequired,
            function (Link $link) use ($pieComposer, $phpEnabledExtensions, $input, $output, $helper, &$anyErrorsHappened): void {
                $extension = ExtensionName::normaliseFromString($link->getTarget());

                if (in_array($extension->name(), $phpEnabledExtensions)) {
                    $output->writeln(sprintf(
                        '%s: <info>%s</info> ✅ Already installed',
                        $link->getDescription(),
                        $link,
                    ));

                    return;
                }

                $output->writeln(sprintf(
                    '%s: <comment>%s</comment> ⚠️  Missing',
                    $link->getDescription(),
                    $link,
                ));

                try {
                    $matches = $this->findMatchingPackages->for($pieComposer, $extension);
                } catch (OutOfRangeException) {
                    $anyErrorsHappened = true;

                    $message = sprintf(
                        '<error>No packages were found for %s</error>',
                        $extension->nameWithExtPrefix(),
                    );

                    if ($output instanceof ConsoleOutputInterface) {
                        $output->getErrorOutput()->writeln($message);

                        return;
                    }

                    $output->writeln($message);

                    return;
                }

                $choiceQuestion = new ChoiceQuestion(
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
                    0,
                );

                $selectedPackageAnswer = (string) $helper->ask($input, $output, $choiceQuestion);

                if ($selectedPackageAnswer === 'None') {
                    $output->writeln('Okay I won\'t install anything for ' . $extension->name());

                    return;
                }

                try {
                    $this->installSelectedPackage->withPieCli(
                        substr($selectedPackageAnswer, 0, (int) strpos($selectedPackageAnswer, ':')),
                        $input,
                        $output,
                    );
                } catch (Throwable $t) {
                    $anyErrorsHappened = true;

                    $message = '<error>' . $t->getMessage() . '</error>';

                    if ($output instanceof ConsoleOutputInterface) {
                        $output->getErrorOutput()->writeln($message);

                        return;
                    }

                    $output->writeln($message);
                }
            },
        );

        $output->writeln(PHP_EOL . 'Finished checking extensions.');

        /**
         * @psalm-suppress TypeDoesNotContainType
         * @psalm-suppress RedundantCondition
         */
        return $anyErrorsHappened ? self::FAILURE : self::SUCCESS;
    }
}
