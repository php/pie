<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\Composer;
use Composer\Package\Version\VersionParser;
use Composer\Repository\ComposerRepository;
use Composer\Repository\PathRepository;
use Composer\Repository\VcsRepository;
use Composer\Util\Platform;
use InvalidArgumentException;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\Platform as PiePlatform;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPhp\PhpizePath;
use Php\Pie\Platform\TargetPlatform;
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
use function strtolower;
use function trim;

use const PHP_VERSION;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class CommandHelper
{
    public const ARG_REQUESTED_PACKAGE_AND_VERSION = 'requested-package-and-version';
    public const OPTION_WITH_PHP_CONFIG            = 'with-php-config';
    public const OPTION_WITH_PHP_PATH              = 'with-php-path';
    public const OPTION_WITH_PHPIZE_PATH           = 'with-phpize-path';
    public const OPTION_WORKING_DIRECTORY          = 'working-dir';
    private const OPTION_MAKE_PARALLEL_JOBS        = 'make-parallel-jobs';
    private const OPTION_SKIP_ENABLE_EXTENSION     = 'skip-enable-extension';
    private const OPTION_FORCE                     = 'force';

    /** @psalm-suppress UnusedConstructor */
    private function __construct()
    {
    }

    public static function configurePhpConfigOptions(Command $command): void
    {
        $command->addOption(
            self::OPTION_WITH_PHP_CONFIG,
            null,
            InputOption::VALUE_REQUIRED,
            'The path to the `php-config` binary to find the target PHP platform on ' . OperatingSystem::NonWindows->asFriendlyName() . ', e.g. --' . self::OPTION_WITH_PHP_CONFIG . '=/usr/bin/php-config7.4',
        );
        $command->addOption(
            self::OPTION_WITH_PHP_PATH,
            null,
            InputOption::VALUE_REQUIRED,
            'The path to the `php` binary to use as the target PHP platform on ' . OperatingSystem::Windows->asFriendlyName() . ', e.g. --' . self::OPTION_WITH_PHP_PATH . '=C:\usr\php7.4.33\php.exe',
        );
        $command->addOption(
            self::OPTION_WITH_PHPIZE_PATH,
            null,
            InputOption::VALUE_REQUIRED,
            'The path to the `phpize` binary to use as the target PHP platform, e.g. --' . self::OPTION_WITH_PHPIZE_PATH . '=/usr/bin/phpize7.4',
        );
    }

    public static function configureDownloadBuildInstallOptions(Command $command, bool $withRequestedPackageAndVersion = true): void
    {
        if ($withRequestedPackageAndVersion) {
            $command->addArgument(
                self::ARG_REQUESTED_PACKAGE_AND_VERSION,
                InputArgument::OPTIONAL,
                'The PIE package name and version constraint to use, in the format {vendor/package}{?:{?version-constraint}{?@stability}}, for example `xdebug/xdebug:^3.4@alpha`, `xdebug/xdebug:@alpha`, `xdebug/xdebug:^3.4`, etc.',
            );
        }

        $command->addOption(
            self::OPTION_MAKE_PARALLEL_JOBS,
            'j',
            InputOption::VALUE_REQUIRED,
            'Override many jobs to run in parallel when running compiling (this is passed to "make -jN" during build). PIE will try to detect this by default.',
        );
        $command->addOption(
            self::OPTION_SKIP_ENABLE_EXTENSION,
            null,
            InputOption::VALUE_NONE,
            'Specify this to skip attempting to enable the extension in php.ini',
        );
        $command->addOption(
            self::OPTION_FORCE,
            null,
            InputOption::VALUE_NONE,
            'To attempt to install a version that doesn\'t match the version constraints from the meta-data, for instance to install an older version than recommended, or when the signature is not available.',
        );

        $command->addOption(
            self::OPTION_WORKING_DIRECTORY,
            'd',
            InputOption::VALUE_REQUIRED,
            'The working directory to use, where applicable. If not specified, the current working directory is used. Only used in certain contexts.',
        );

        self::configurePhpConfigOptions($command);

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
        $withPhpConfig          = $input->getOption(self::OPTION_WITH_PHP_CONFIG);
        $specifiedWithPhpConfig = is_string($withPhpConfig) && $withPhpConfig !== '';
        /** @var mixed $withPhpPath */
        $withPhpPath          = $input->getOption(self::OPTION_WITH_PHP_PATH);
        $specifiedWithPhpPath = is_string($withPhpPath) && $withPhpPath !== '';

        if (Platform::isWindows() && $specifiedWithPhpConfig) {
            throw new InvalidArgumentException('The --with-php-config=/path/to/php-config cannot be used on Windows, use --with-php-path=/path/to/php instead.');
        }

        if (! Platform::isWindows() && $specifiedWithPhpPath && ! $specifiedWithPhpConfig) {
            throw new InvalidArgumentException('The --with-php-path=/path/to/php cannot be used on non-Windows, use --with-php-config=/path/to/php-config instead.');
        }

        if (Platform::isWindows() && $input->hasOption(self::OPTION_WITH_PHPIZE_PATH)) {
            /** @var mixed $withPhpizePath */
            $withPhpizePath = $input->getOption(self::OPTION_WITH_PHPIZE_PATH);

            if (is_string($withPhpizePath) && trim($withPhpizePath) !== '') {
                throw new InvalidArgumentException('The --with-phpize-path=/path/to/phpize cannot be used on Windows.');
            }
        }

        if ($specifiedWithPhpConfig) {
            $phpBinaryPath = PhpBinaryPath::fromPhpConfigExecutable($withPhpConfig);
        }

        if ($specifiedWithPhpPath) {
            $phpBinaryPath = PhpBinaryPath::fromPhpBinaryPath($withPhpPath);
        }

        $makeParallelJobs = null; /** `null` means {@see TargetPlatform} will try to auto-detect */
        if ($input->hasOption(self::OPTION_MAKE_PARALLEL_JOBS)) {
            $makeParallelJobsOptions = (int) $input->getOption(self::OPTION_MAKE_PARALLEL_JOBS);
            if ($makeParallelJobsOptions > 0) {
                $makeParallelJobs = $makeParallelJobsOptions;
            }
        }

        $targetPlatform = TargetPlatform::fromPhpBinaryPath($phpBinaryPath, $makeParallelJobs);

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
        $output->writeln(
            sprintf(
                '<info>Using pie.json:</info> %s',
                PiePlatform::getPieJsonFilename($targetPlatform),
            ),
            OutputInterface::VERBOSITY_VERBOSE,
        );

        return $targetPlatform;
    }

    public static function determineAttemptToSetupIniFile(InputInterface $input): bool
    {
        return ! $input->hasOption(self::OPTION_SKIP_ENABLE_EXTENSION) || ! $input->getOption(self::OPTION_SKIP_ENABLE_EXTENSION);
    }

    public static function determineForceInstallingPackageVersion(InputInterface $input): bool
    {
        return $input->hasOption(self::OPTION_FORCE) && $input->getOption(self::OPTION_FORCE);
    }

    public static function determinePhpizePathFromInputs(InputInterface $input): PhpizePath|null
    {
        if ($input->hasOption(self::OPTION_WITH_PHPIZE_PATH)) {
            $phpizePathOption = (string) $input->getOption(self::OPTION_WITH_PHPIZE_PATH);
            if (trim($phpizePathOption) !== '') {
                /** @psalm-suppress ArgumentTypeCoercion */
                return new PhpizePath($phpizePathOption);
            }
        }

        return null;
    }

    public static function requestedNameAndVersionPair(InputInterface $input): RequestedPackageAndVersion
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

        return new RequestedPackageAndVersion(
            $requestedNameAndVersionPair['name'],
            $requestedNameAndVersionPair['version'],
        );
    }

    public static function bindConfigureOptionsFromPackage(Command $command, Package $package, InputInterface $input): void
    {
        foreach ($package->configureOptions() as $configureOption) {
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
        foreach ($package->configureOptions() as $configureOption) {
            if (! $input->hasOption($configureOption->name)) {
                continue;
            }

            $value = $input->getOption($configureOption->name);

            if ($configureOption->needsValue) {
                if (is_string($value) && $value !== '') {
                    $configureOptionsValues[] = '--' . $configureOption->name . '=' . $value;
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

    public static function listRepositories(Composer $composer, OutputInterface $output): void
    {
        $output->writeln('The following repositories are in use for this Target PHP:');

        foreach ($composer->getRepositoryManager()->getRepositories() as $repo) {
            if ($repo instanceof ComposerRepository) {
                $repoConfig = $repo->getRepoConfig();

                $repoUrl = array_key_exists('url', $repoConfig) && is_string($repoConfig['url']) && $repoConfig['url'] !== '' ? $repoConfig['url'] : null;

                if ($repoUrl === 'https://repo.packagist.org') {
                    $output->writeln('  - Packagist');
                    continue;
                }

                $output->writeln(sprintf('  - Composer (%s)', $repoUrl ?? 'no url?'));
                continue;
            }

            if ($repo instanceof VcsRepository) {
                /** @psalm-suppress InternalMethod */
                $output->writeln(sprintf(
                    '  - VCS Repository (%s)',
                    $repo->getDriver()?->getUrl() ?? 'no url?',
                ));
                continue;
            }

            if (! $repo instanceof PathRepository) {
                continue;
            }

            $repoConfig = $repo->getRepoConfig();
            $output->writeln(sprintf(
                '  - Path Repository (%s)',
                array_key_exists('url', $repoConfig) && is_string($repoConfig['url']) && $repoConfig['url'] !== '' ? $repoConfig['url'] : 'no path?',
            ));
        }
    }
}
