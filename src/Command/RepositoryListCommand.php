<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\IO\IOInterface;
use Composer\IO\NullIO;
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
        private readonly IOInterface $io,
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
        CommandHelper::applyNoCacheOptionIfSet($input, $this->io);

        CommandHelper::listRepositories(
            PieComposerFactory::createPieComposer(
                $this->container,
                PieComposerRequest::noOperation(
                    new NullIO(),
                    CommandHelper::determineTargetPlatformFromInputs($input, $this->io),
                ),
            ),
            $this->io,
        );

        return 0;
    }
}
