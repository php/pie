<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\IO\IOInterface;
use OutOfRangeException;
use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\Installing\InstallForPhpProject\FindMatchingPackages;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function count;
use function implode;
use function is_string;
use function sprintf;

#[AsCommand(
    name: 'search',
    description: 'Search for PIE-compatible packages by name or keyword.',
)]
final class SearchCommand extends Command
{
    private const ARG_SEARCH_TERM = 'search-term';

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly FindMatchingPackages $findMatchingPackages,
        private readonly IOInterface $io,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        CommandHelper::configurePhpConfigOptions($this);

        $this->addArgument(
            self::ARG_SEARCH_TERM,
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'The term(s) to search for, matched against package name, description and keywords.',
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetPlatform = CommandHelper::determineTargetPlatformFromInputs($input, $this->io);

        CommandHelper::applyNoCacheOptionIfSet($input, $this->io);

        $searchTerms = $input->getArgument(self::ARG_SEARCH_TERM);
        Assert::isArray($searchTerms);
        Assert::allStringNotEmpty($searchTerms);

        $searchTerm = implode(' ', $searchTerms);
        Assert::stringNotEmpty($searchTerm);

        $composer = PieComposerFactory::createPieComposer(
            $this->container,
            PieComposerRequest::noOperation(
                $this->io,
                $targetPlatform,
            ),
        );

        try {
            $matches = $this->findMatchingPackages->bySearching($composer, $searchTerm);
        } catch (OutOfRangeException) {
            $this->io->write(sprintf('No packages found matching "%s".', $searchTerm));

            return Command::SUCCESS;
        }

        $matches = CommandHelper::augmentMatchesWithExtensionName($composer, $matches, $this->io);

        $this->io->write(sprintf("\nFound %d package(s) matching \"%s\":", count($matches), $searchTerm));
        foreach ($matches as $match) {
            $this->io->write(sprintf(
                ' - <info>%s</info>%s: %s',
                $match['name'],
                array_key_exists('extension-name', $match) && is_string($match['extension-name'])
                    ? ' (provides extension: ' . $match['extension-name'] . ')'
                    : '',
                $match['description'] ?? 'no description available',
            ));
        }

        return Command::SUCCESS;
    }
}
