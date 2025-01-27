<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Php\Pie\ComposerIntegration\PieComposerFactory;
use Php\Pie\ComposerIntegration\PieComposerRequest;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'repository:list',
    description: 'List the package repositories that PIE uses.',
)]
final class RepositoryListCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        CommandHelper::configurePhpConfigOptions($this);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
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
