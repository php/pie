<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\Package\Version\VersionParser;
use InvalidArgumentException;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\Downloading\DownloadAndExtract;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function is_array;
use function is_string;
use function reset;
use function sprintf;

use const PHP_VERSION;

#[AsCommand(
    name: 'download',
    description: 'Same behaviour as build, but puts the files in a local directory for manual building and installation.',
)]
final class DownloadCommand extends Command
{
    private const ARG_REQUESTED_PACKAGE_AND_VERSION = 'requested-package-and-version';
    private const OPTION_WITH_PHP_CONFIG            = 'with-php-config';
    private const OPTION_WITH_PHP_PATH              = 'with-php-path';

    public function __construct(
        private readonly DependencyResolver $dependencyResolver,
        private readonly DownloadAndExtract $downloadAndExtract,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        $this->addArgument(
            self::ARG_REQUESTED_PACKAGE_AND_VERSION,
            InputArgument::REQUIRED,
            'The extension name and version constraint to use, in the format {ext-name}{?:{?version-constraint}{?@stability}}, for example `xdebug/xdebug:^3.4@alpha`, `xdebug/xdebug:@alpha`, `xdebug/xdebug:^3.4`, etc.',
        );
        $this->addOption(
            self::OPTION_WITH_PHP_CONFIG,
            null,
            InputOption::VALUE_OPTIONAL,
            'The path to the `php-config` binary to find the target PHP platform, e.g. --' . self::OPTION_WITH_PHP_CONFIG . '=/usr/bin/php-config7.4',
        );
        $this->addOption(
            self::OPTION_WITH_PHP_PATH,
            null,
            InputOption::VALUE_OPTIONAL,
            'The path to the `php` binary to use as the target PHP platform, e.g. --' . self::OPTION_WITH_PHP_PATH . '=C:\usr\php7.4.33\php.exe',
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $phpBinaryPath = PhpBinaryPath::fromCurrentProcess();

        /** @var mixed $withPhpConfig */
        $withPhpConfig = $input->getOption(self::OPTION_WITH_PHP_CONFIG);
        if (is_string($withPhpConfig) && $withPhpConfig !== '') {
            $phpBinaryPath = PhpBinaryPath::fromPhpConfigExecutable($withPhpConfig);
        }

        /** @var mixed $withPhpPath */
        $withPhpPath = $input->getOption(self::OPTION_WITH_PHP_PATH);
        if (is_string($withPhpPath) && $withPhpPath !== '') {
            $phpBinaryPath = PhpBinaryPath::fromPhpBinaryPath($withPhpPath);
        }

        $targetPlatform = TargetPlatform::fromPhpBinaryPath($phpBinaryPath);

        $output->writeln(sprintf('<info>You are running PHP %s</info>', PHP_VERSION));
        $output->writeln(sprintf('<info>Target PHP installation:</info> %s (from %s)', $phpBinaryPath->version(), $phpBinaryPath->phpBinaryPath));
        $output->writeln(sprintf(
            '<info>Platform:</info> %s, %s, %s%s',
            $targetPlatform->operatingSystem->name,
            $targetPlatform->architecture->name,
            $targetPlatform->threadSafety->name,
            $targetPlatform->windowsCompiler !== null ? ', ' . $targetPlatform->windowsCompiler->name : '',
        ));

        $requestedNameAndVersionPair = $this->requestedNameAndVersionPair($input);

        $package = ($this->dependencyResolver)(
            $targetPlatform,
            $requestedNameAndVersionPair['name'],
            $requestedNameAndVersionPair['version'],
        );

        $output->writeln(sprintf('<info>Found package:</info> %s which provides <info>%s</info>', $package->prettyNameAndVersion(), $package->extensionName->nameWithExtPrefix()));

        $downloadedPackage = ($this->downloadAndExtract)($targetPlatform, $package);

        $output->writeln(sprintf(
            '<info>Extracted %s source to:</info> %s',
            $downloadedPackage->package->prettyNameAndVersion(),
            $downloadedPackage->extractedSourcePath,
        ));

        return Command::SUCCESS;
    }

    /** @return array{name: non-empty-string, version: non-empty-string|null} */
    private function requestedNameAndVersionPair(InputInterface $input): array
    {
        $requestedPackageString = $input->getArgument(self::ARG_REQUESTED_PACKAGE_AND_VERSION);

        if (! is_string($requestedPackageString) || $requestedPackageString === '') {
            throw new InvalidArgumentException('No package was requested for installation');
        }

        $nameAndVersionPairs         = (new VersionParser())
            ->parseNameVersionPairs([$requestedPackageString]);
        $requestedNameAndVersionPair = reset($nameAndVersionPairs);

        if (! is_array($requestedNameAndVersionPair)) {
            throw new InvalidArgumentException('Failed to parse the name/version pair');
        }

        if (! array_key_exists('version', $requestedNameAndVersionPair)) {
            $requestedNameAndVersionPair['version'] = null;
        }

        Assert::stringNotEmpty($requestedNameAndVersionPair['name']);
        Assert::nullOrStringNotEmpty($requestedNameAndVersionPair['version']);

        return $requestedNameAndVersionPair;
    }
}
