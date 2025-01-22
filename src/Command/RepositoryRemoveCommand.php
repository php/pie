<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Php\Pie\ComposerIntegration\PieJsonEditor;
use Php\Pie\Platform;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'repository:remove',
    description: 'Remove a repository for packages that PIE can use.',
)]
final class RepositoryRemoveCommand extends Command
{
    private const ARG_URL = 'url';

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        CommandHelper::configurePhpConfigOptions($this);

        $this->addArgument(
            self::ARG_URL,
            InputArgument::REQUIRED,
            'Specify the URL of the repository, e.g. a Github/Gitlab URL, or a filesystem path',
        );
        $this->addUsage('lol');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetPlatform  = CommandHelper::determineTargetPlatformFromInputs($input, $output);
        $pieJsonFilename = Platform::getPieJsonFilename($targetPlatform);

        $url = (string) $input->getArgument(self::ARG_URL);
        Assert::stringNotEmpty($url);

        (new PieJsonEditor($pieJsonFilename))->removeRepository($url);

        CommandHelper::listRepositories(
            PieComposerFactory::createPieComposer(
                $this->container,
                PieComposerRequest::noOperation(
                    $output,
                    CommandHelper::determineTargetPlatformFromInputs($input, $output),
                ),
            ),
            $output,
        );

        return 0;
    }
}
