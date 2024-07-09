<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\Package\Version\VersionParser;
use InvalidArgumentException;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadAndExtract;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function escapeshellarg;
use function is_array;
use function is_string;
use function reset;
use function sprintf;
use function strtolower;

use const PHP_VERSION;

/** @psalm-type RequestedNameAndVersionPair = array{name: non-empty-string, version: non-empty-string|null} */
final class CommandHelper
{
    private const ARG_REQUESTED_PACKAGE_AND_VERSION = 'requested-package-and-version';
    private const OPTION_WITH_PHP_CONFIG            = 'with-php-config';
    private const OPTION_WITH_PHP_PATH              = 'with-php-path';

    /** @psalm-suppress UnusedConstructor */
    private function __construct()
    {
    }

    public static function configureOptions(Command $command): void
    {
        $command->addArgument(
            self::ARG_REQUESTED_PACKAGE_AND_VERSION,
            InputArgument::REQUIRED,
            'The extension name and version constraint to use, in the format {ext-name}{?:{?version-constraint}{?@stability}}, for example `xdebug/xdebug:^3.4@alpha`, `xdebug/xdebug:@alpha`, `xdebug/xdebug:^3.4`, etc.',
        );
        $command->addOption(
            self::OPTION_WITH_PHP_CONFIG,
            null,
            InputOption::VALUE_OPTIONAL,
            'The path to the `php-config` binary to find the target PHP platform on ' . OperatingSystem::NonWindows->asFriendlyName() . ', e.g. --' . self::OPTION_WITH_PHP_CONFIG . '=/usr/bin/php-config7.4',
        );
        $command->addOption(
            self::OPTION_WITH_PHP_PATH,
            null,
            InputOption::VALUE_OPTIONAL,
            'The path to the `php` binary to use as the target PHP platform on ' . OperatingSystem::Windows->asFriendlyName() . ', e.g. --' . self::OPTION_WITH_PHP_PATH . '=C:\usr\php7.4.33\php.exe',
        );

        /**
         * Allows additional options for the `./configure` command to be passed here.
         * Note, this means you probably need to call {@see self::validateInput()} to validate the input manually...
         */
        $command->ignoreValidationErrors();
    }

    public static function validateInput(InputInterface $input, Command $command): void
    {
        $input->bind($command->getDefinition());
    }

    public static function determineTargetPlatformFromInputs(InputInterface $input, OutputInterface $output): TargetPlatform
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
        $output->writeln(sprintf(
            '<info>Target PHP installation:</info> %s %s%s, on %s %s (from %s)',
            $phpBinaryPath->version(),
            $targetPlatform->threadSafety->asShort(),
            strtolower($targetPlatform->windowsCompiler !== null ? ', ' . $targetPlatform->windowsCompiler->name : ''),
            $targetPlatform->operatingSystem->asFriendlyName(),
            $targetPlatform->architecture->name,
            $phpBinaryPath->phpBinaryPath,
        ));

        return $targetPlatform;
    }

    /** @return RequestedNameAndVersionPair */
    public static function requestedNameAndVersionPair(InputInterface $input): array
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

    /** @param RequestedNameAndVersionPair $requestedNameAndVersionPair */
    public static function downloadPackage(
        DependencyResolver $dependencyResolver,
        TargetPlatform $targetPlatform,
        array $requestedNameAndVersionPair,
        DownloadAndExtract $downloadAndExtract,
        OutputInterface $output,
    ): DownloadedPackage {
        $package = ($dependencyResolver)(
            $targetPlatform,
            $requestedNameAndVersionPair['name'],
            $requestedNameAndVersionPair['version'],
        );

        $output->writeln(sprintf('<info>Found package:</info> %s which provides <info>%s</info>', $package->prettyNameAndVersion(), $package->extensionName->nameWithExtPrefix()));

        return ($downloadAndExtract)($targetPlatform, $package);
    }

    public static function bindConfigureOptionsFromPackage(Command $command, Package $package, InputInterface $input): void
    {
        foreach ($package->configureOptions as $configureOption) {
            $command->addOption(
                $configureOption->name,
                null,
                $configureOption->needsValue ? InputOption::VALUE_REQUIRED : InputOption::VALUE_NONE,
                $configureOption->description,
            );
        }

        self::validateInput($input, $command);
    }

    /** @return list<non-empty-string> */
    public static function processConfigureOptionsFromInput(Package $package, InputInterface $input): array
    {
        $configureOptionsValues = [];
        foreach ($package->configureOptions as $configureOption) {
            if (! $input->hasOption($configureOption->name)) {
                continue;
            }

            $value = $input->getOption($configureOption->name);

            if ($configureOption->needsValue) {
                if (is_string($value) && $value !== '') {
                    $configureOptionsValues[] = '--' . $configureOption->name . '=' . escapeshellarg($value);
                }

                continue;
            }

            Assert::boolean($value);
            if ($value !== true) {
                continue;
            }

            $configureOptionsValues[] = '--' . $configureOption->name;
        }

        return $configureOptionsValues;
    }
}
