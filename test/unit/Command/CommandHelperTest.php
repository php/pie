<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Command;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Repository\ComposerRepository;
use Composer\Repository\PathRepository;
use Composer\Repository\RepositoryManager;
use Composer\Repository\Vcs\GitHubDriver;
use Composer\Repository\VcsRepository;
use Composer\Util\Platform;
use InvalidArgumentException;
use Php\Pie\Command\CommandHelper;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

use function array_combine;
use function array_map;
use function str_replace;
use function trim;

#[CoversClass(CommandHelper::class)]
final class CommandHelperTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: non-empty-string, 2: non-empty-string|null}>
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public static function validPackageAndVersions(): array
    {
        $packages = [
            ['php/test-pie-ext', 'php/test-pie-ext', null],
            ['php/test-pie-ext:^1.2', 'php/test-pie-ext', '^1.2'],
            ['php/test-pie-ext:@alpha', 'php/test-pie-ext', '@alpha'],
            ['php/test-pie-ext:~1.2.1', 'php/test-pie-ext', '~1.2.1'],
            ['php/test-pie-ext:*', 'php/test-pie-ext', '*'],
            ['php/test-pie-ext:1.2.3', 'php/test-pie-ext', '1.2.3'],
        ];

        return array_combine(
            array_map(static fn (array $data) => $data[0], $packages),
            $packages,
        );
    }

    /**
     * @param non-empty-string      $expectedPackage
     * @param non-empty-string|null $expectedVersion
     */
    #[DataProvider('validPackageAndVersions')]
    public function testRequestedNameAndVersionPair(string $requestedPackageAndVersion, string $expectedPackage, string|null $expectedVersion): void
    {
        $input = $this->createMock(InputInterface::class);

        $input->expects(self::once())
            ->method('getArgument')
            ->with('requested-package-and-version')
            ->willReturn($requestedPackageAndVersion);

        self::assertEquals(
            new RequestedPackageAndVersion($expectedPackage, $expectedVersion),
            CommandHelper::requestedNameAndVersionPair($input),
        );
    }

    public function testInvalidRequestedNameAndVersionPairThrowsExceptionWhenNoPackageProvided(): void
    {
        $input = $this->createMock(InputInterface::class);

        $input->expects(self::once())
            ->method('getArgument')
            ->with('requested-package-and-version')
            ->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No package was requested for installation');
        CommandHelper::requestedNameAndVersionPair($input);
    }

    public function testBindingConfigurationOptionsFromPackage(): void
    {
        self::markTestIncomplete(__METHOD__);
    }

    public function testProcessingConfigureOptionsFromInput(): void
    {
        $composerPackage = $this->createMock(CompletePackage::class);
        $composerPackage->method('getPrettyName')->willReturn('foo/bar');
        $composerPackage->method('getPrettyVersion')->willReturn('1.0.0');
        $composerPackage->method('getType')->willReturn('php-ext');
        $composerPackage->method('getPhpExt')->willReturn([
            'configure-options' => [
                [
                    'name' => 'with-stuff',
                    'needs-value' => true,
                ],
                ['name' => 'enable-thing'],
            ],
        ]);
        $package = Package::fromComposerCompletePackage($composerPackage);

        $inputDefinition = new InputDefinition();
        $inputDefinition->addOption(new InputOption('with-stuff', null, InputOption::VALUE_REQUIRED));
        $inputDefinition->addOption(new InputOption('enable-thing', null, InputOption::VALUE_NONE));

        $input = new ArrayInput(['--with-stuff' => 'lolz', '--enable-thing' => true], $inputDefinition);

        $options = CommandHelper::processConfigureOptionsFromInput($package, $input);

        self::assertSame(
            [
                '--with-stuff=lolz',
                '--enable-thing',
            ],
            $options,
        );
    }

    #[RequiresOperatingSystemFamily('Windows')]
    public function testWindowsMachinesCannotUseWithPhpConfigOption(): void
    {
        $command = new Command();
        $input   = new ArrayInput(['--with-php-config' => 'C:\path\to\php-config']);
        $output  = new NullOutput();
        CommandHelper::configureDownloadBuildInstallOptions($command);
        CommandHelper::validateInput($input, $command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The --with-php-config=/path/to/php-config cannot be used on Windows, use --with-php-path=/path/to/php instead.');
        CommandHelper::determineTargetPlatformFromInputs($input, $output);
    }

    public function testNonWindowsMachinesCannotUseWithPhpPathOption(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('This test can only run on non-Windows');
        }

        $command = new Command();
        $input   = new ArrayInput(['--with-php-path' => '/usr/bin/php']);
        $output  = new NullOutput();
        CommandHelper::configureDownloadBuildInstallOptions($command);
        CommandHelper::validateInput($input, $command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The --with-php-path=/path/to/php cannot be used on non-Windows, use --with-php-config=/path/to/php-config instead.');
        CommandHelper::determineTargetPlatformFromInputs($input, $output);
    }

    #[RequiresOperatingSystemFamily('Windows')]
    public function testWindowsMachinesCannotUseWithPhpizePathOption(): void
    {
        $command = new Command();
        $input   = new ArrayInput(['--with-phpize-path' => 'C:\path\to\phpize']);
        $output  = new NullOutput();
        CommandHelper::configureDownloadBuildInstallOptions($command);
        CommandHelper::validateInput($input, $command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The --with-phpize-path=/path/to/phpize cannot be used on Windows.');
        CommandHelper::determineTargetPlatformFromInputs($input, $output);
    }

    public function testListRepositories(): void
    {
        $output = new BufferedOutput();

        $packagistRepo = $this->createMock(ComposerRepository::class);
        $packagistRepo->method('getRepoConfig')->willReturn(['url' => 'https://repo.packagist.org']);

        $privatePackagistRepo = $this->createMock(ComposerRepository::class);
        $privatePackagistRepo->method('getRepoConfig')->willReturn(['url' => 'https://repo.packagist.com/example']);

        $githubRepoDriver = $this->createMock(GitHubDriver::class);
        $githubRepoDriver->method('getUrl')->willReturn('https://github.com/php/pie');

        $vcsRepo = $this->createMock(VcsRepository::class);
        $vcsRepo->method('getDriver')->willReturn($githubRepoDriver);

        $pathRepo = $this->createMock(PathRepository::class);
        $pathRepo->method('getRepoConfig')->willReturn(['url' => '/path/to/repo']);

        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getRepositories')->willReturn([
            $packagistRepo,
            $privatePackagistRepo,
            $vcsRepo,
            $pathRepo,
        ]);

        $composer = $this->createMock(Composer::class);
        $composer->method('getRepositoryManager')->willReturn($repoManager);

        CommandHelper::listRepositories($composer, $output);

        self::assertSame(
            str_replace("\r\n", "\n", <<<'OUTPUT'
            The following repositories are in use for this Target PHP:
              - Packagist
              - Composer (https://repo.packagist.com/example)
              - VCS Repository (https://github.com/php/pie)
              - Path Repository (/path/to/repo)
            OUTPUT),
            str_replace("\r\n", "\n", trim($output->fetch())),
        );
    }
}
