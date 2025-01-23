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

use function realpath;

#[AsCommand(
    name: 'repository:add',
    description: 'Add a new repository for packages that PIE can use.',
)]
final class RepositoryAddCommand extends Command
{
    private const ARG_TYPE = 'type';
    private const ARG_URL  = 'url';

    private const ALLOWED_TYPES = ['vcs', 'path', 'composer'];

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
            self::ARG_TYPE,
            InputArgument::REQUIRED,
            'Specify the type of the repository, e.g. vcs, path, composer',
        );
        $this->addArgument(
            self::ARG_URL,
            InputArgument::REQUIRED,
            'Specify the URL of the repository, e.g. a Github/Gitlab URL, a filesystem path, or Private Packagist URL',
        );
        $this->addUsage('lol');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetPlatform  = CommandHelper::determineTargetPlatformFromInputs($input, $output);
        $pieJsonFilename = Platform::getPieJsonFilename($targetPlatform);

        $type = (string) $input->getArgument(self::ARG_TYPE);
        /** @psalm-var 'vcs'|'path'|'composer' $type */
        Assert::inArray($type, self::ALLOWED_TYPES);

        $url = $originalUrl = (string) $input->getArgument(self::ARG_URL);

        if ($type === 'path') {
            $url = realpath($originalUrl);
        }

        Assert::stringNotEmpty($url, 'Could not resolve ' . $originalUrl . ' to a real path');

        (new PieJsonEditor($pieJsonFilename))->addRepository($type, $url);

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
