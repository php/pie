<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Command;

use Composer\Composer;
use Composer\IO\BufferIO;
use Composer\IO\NullIO;
use Composer\Package\CompletePackageInterface;
use Composer\Repository\ComposerRepository;
use Composer\Repository\PathRepository;
use Composer\Repository\RepositoryManager;
use Composer\Repository\Vcs\GitHubDriver;
use Composer\Repository\VcsRepository;
use Composer\Util\Platform;
use InvalidArgumentException;
use Php\Pie\Command\CommandHelper;
use Php\Pie\Command\ConfigureOptionCollision;
use Php\Pie\DependencyResolver\BundledPhpExtensionRefusal;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\DependencyResolver\ResolvedPackageRequest;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use Php\Pie\Downloading\DownloadUrlMethod;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use function array_combine;
use function array_map;
use function str_replace;
use function trim;

#[CoversClass(CommandHelper::class)]
final class CommandHelperTest extends TestCase
{
    /** @return array<string, array{0: string, 1: non-empty-string, 2: non-empty-string|null}> */
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
            ->willReturn([$requestedPackageAndVersion]);

        self::assertEquals(
            [new RequestedPackageAndVersion($expectedPackage, $expectedVersion)],
            CommandHelper::requestedNameAndVersionPairs($input),
        );
    }

    public function testRequestedNameAndVersionPairSupportsMultiple(): void
    {
        $input = $this->createMock(InputInterface::class);

        $input->expects(self::once())
            ->method('getArgument')
            ->with('requested-package-and-version')
            ->willReturn([
                'a/ext',
                'b/ext:^1.2',
                'c/ext:*',
                'd/ext:@alpha',
                'e/ext:1.2.3',
            ]);

        self::assertEquals(
            [
                new RequestedPackageAndVersion('a/ext', null),
                new RequestedPackageAndVersion('b/ext', '^1.2'),
                new RequestedPackageAndVersion('c/ext', '*'),
                new RequestedPackageAndVersion('d/ext', '@alpha'),
                new RequestedPackageAndVersion('e/ext', '1.2.3'),
            ],
            CommandHelper::requestedNameAndVersionPairs($input),
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
        CommandHelper::requestedNameAndVersionPairs($input);
    }

    public function testBindingConfigurationOptionsFromPackage(): void
    {
        self::markTestIncomplete(__METHOD__);
    }

    private static function packageNamed(string $prettyName, string $prettyVersion): Package
    {
        $composerPackage = self::createStub(CompletePackageInterface::class);
        $composerPackage->method('getPrettyName')->willReturn($prettyName);
        $composerPackage->method('getPrettyVersion')->willReturn($prettyVersion);
        $composerPackage->method('getType')->willReturn('php-ext');

        return Package::fromComposerCompletePackage($composerPackage);
    }

    public function testResolveRequestedPackagesResolvesEachRequestedPackageAndWritesOutput(): void
    {
        $requestedA = new RequestedPackageAndVersion('foo/bar', null);
        $requestedB = new RequestedPackageAndVersion('baz/qux', '^1.0');

        $resolvedA = new ResolvedPackageRequest(self::packageNamed('foo/bar', '1.0.0'), $requestedA);
        $resolvedB = new ResolvedPackageRequest(self::packageNamed('baz/qux', '2.0.0'), $requestedB);

        $composer       = $this->createMock(Composer::class);
        $targetPlatform = $this->createMock(TargetPlatform::class);

        $dependencyResolver = $this->createMock(DependencyResolver::class);
        $dependencyResolver->expects(self::exactly(2))
            ->method('__invoke')
            ->willReturnCallback(
                static function (Composer $givenComposer, TargetPlatform $givenTargetPlatform, RequestedPackageAndVersion $requested, bool $force) use ($composer, $targetPlatform, $requestedA, $requestedB, $resolvedA, $resolvedB): ResolvedPackageRequest {
                    self::assertSame($composer, $givenComposer);
                    self::assertSame($targetPlatform, $givenTargetPlatform);
                    self::assertTrue($force);

                    if ($requested === $requestedA) {
                        return $resolvedA;
                    }

                    self::assertSame($requestedB, $requested);

                    return $resolvedB;
                },
            );

        $io = new BufferIO();

        $resolvedPackages = CommandHelper::resolveRequestedPackages(
            $dependencyResolver,
            $io,
            $composer,
            $targetPlatform,
            [$requestedA, $requestedB],
            true,
        );

        self::assertSame([$resolvedA, $resolvedB], $resolvedPackages);
        self::assertSame(
            "Found package: foo/bar:1.0.0 which provides ext-bar\nFound package: baz/qux:2.0.0 which provides ext-qux",
            str_replace("\r\n", "\n", trim($io->getOutput())),
        );
    }

    public function testResolveRequestedPackagesPropagatesUnableToResolveRequirement(): void
    {
        $requested = new RequestedPackageAndVersion('foo/bar', null);

        $exception = new UnableToResolveRequirement('Could not resolve foo/bar', $requested);

        $dependencyResolver = $this->createMock(DependencyResolver::class);
        $dependencyResolver->method('__invoke')->willThrowException($exception);

        $this->expectExceptionObject($exception);

        CommandHelper::resolveRequestedPackages(
            $dependencyResolver,
            new NullIO(),
            $this->createMock(Composer::class),
            $this->createMock(TargetPlatform::class),
            [$requested],
            false,
        );
    }

    public function testResolveRequestedPackagesPropagatesBundledPhpExtensionRefusal(): void
    {
        $requested = new RequestedPackageAndVersion('foo/bar', null);

        $exception = BundledPhpExtensionRefusal::forPackage(self::packageNamed('foo/bar', '1.0.0'));

        $dependencyResolver = $this->createMock(DependencyResolver::class);
        $dependencyResolver->method('__invoke')->willThrowException($exception);

        $this->expectExceptionObject($exception);

        CommandHelper::resolveRequestedPackages(
            $dependencyResolver,
            new NullIO(),
            $this->createMock(Composer::class),
            $this->createMock(TargetPlatform::class),
            [$requested],
            false,
        );
    }

    public function testProcessingConfigureOptionsFromInput(): void
    {
        $composerPackage = $this->createMock(CompletePackageInterface::class);
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

        $options = CommandHelper::processConfigureOptionsFromInput([$package], $input);

        self::assertSame(
            [
                'foo/bar' => [
                    '--with-stuff=lolz',
                    '--enable-thing',
                ],
            ],
            $options,
        );
    }

    public function testBindConfigureOptionsFromPackageThrowsWhenTwoPackagesDeclareSameOptionName(): void
    {
        $composerPackageA = $this->createMock(CompletePackageInterface::class);
        $composerPackageA->method('getPrettyName')->willReturn('foo/bar');
        $composerPackageA->method('getPrettyVersion')->willReturn('1.0.0');
        $composerPackageA->method('getType')->willReturn('php-ext');
        $composerPackageA->method('getPhpExt')->willReturn([
            'configure-options' => [
                ['name' => 'with-stuff', 'needs-value' => true],
            ],
        ]);
        $packageA = Package::fromComposerCompletePackage($composerPackageA);

        $composerPackageB = $this->createMock(CompletePackageInterface::class);
        $composerPackageB->method('getPrettyName')->willReturn('baz/qux');
        $composerPackageB->method('getPrettyVersion')->willReturn('2.0.0');
        $composerPackageB->method('getType')->willReturn('php-ext');
        $composerPackageB->method('getPhpExt')->willReturn([
            'configure-options' => [
                ['name' => 'with-stuff'],
            ],
        ]);
        $packageB = Package::fromComposerCompletePackage($composerPackageB);

        $command = new Command();
        $input   = new ArrayInput([]);

        $this->expectException(ConfigureOptionCollision::class);
        $this->expectExceptionMessage('Both foo/bar and baz/qux declare a configure option named --with-stuff');

        CommandHelper::bindConfigureOptionsFromPackage($command, [$packageA, $packageB], $input);
    }

    #[RequiresOperatingSystemFamily('Windows')]
    public function testWindowsMachinesCannotUseWithPhpConfigOption(): void
    {
        $command = new Command();
        $input   = new ArrayInput(['--with-php-config' => 'C:\path\to\php-config']);
        $io      = new NullIO();
        CommandHelper::configureDownloadBuildInstallOptions($command);
        CommandHelper::validateInput($input, $command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The --with-php-config=/path/to/php-config cannot be used on Windows, use --with-php-path=/path/to/php instead.');
        CommandHelper::determineTargetPlatformFromInputs($input, $io);
    }

    public function testNonWindowsMachinesCannotUseWithPhpPathOption(): void
    {
        if (Platform::isWindows()) {
            self::markTestSkipped('This test can only run on non-Windows');
        }

        $command = new Command();
        $input   = new ArrayInput(['--with-php-path' => '/usr/bin/php']);
        $io      = new NullIO();
        CommandHelper::configureDownloadBuildInstallOptions($command);
        CommandHelper::validateInput($input, $command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The --with-php-path=/path/to/php cannot be used on non-Windows, use --with-php-config=/path/to/php-config instead.');
        CommandHelper::determineTargetPlatformFromInputs($input, $io);
    }

    #[RequiresOperatingSystemFamily('Windows')]
    public function testWindowsMachinesCannotUseWithPhpizePathOption(): void
    {
        $command = new Command();
        $input   = new ArrayInput(['--with-phpize-path' => 'C:\path\to\phpize']);
        $io      = new NullIO();
        CommandHelper::configureDownloadBuildInstallOptions($command);
        CommandHelper::validateInput($input, $command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The --with-phpize-path=/path/to/phpize cannot be used on Windows.');
        CommandHelper::determineTargetPlatformFromInputs($input, $io);
    }

    public function testDetermineSuppressedDownloadUrlMethodsDefaultsToEmpty(): void
    {
        $command = new Command();
        $input   = new ArrayInput([]);
        CommandHelper::configureDownloadBuildInstallOptions($command);
        CommandHelper::validateInput($input, $command);

        self::assertSame([], CommandHelper::determineSuppressedDownloadUrlMethods($input));
    }

    public function testDetermineSuppressedDownloadUrlMethodsParsesGivenValues(): void
    {
        $command = new Command();
        $input   = new ArrayInput(['--suppress-download-url-method' => ['composer-default', 'pre-packaged-source']]);
        CommandHelper::configureDownloadBuildInstallOptions($command);
        CommandHelper::validateInput($input, $command);

        self::assertSame(
            [DownloadUrlMethod::ComposerDefaultDownload, DownloadUrlMethod::PrePackagedSourceDownload],
            CommandHelper::determineSuppressedDownloadUrlMethods($input),
        );
    }

    public function testDetermineSuppressedDownloadUrlMethodsThrowsForInvalidValue(): void
    {
        $command = new Command();
        $input   = new ArrayInput(['--suppress-download-url-method' => ['not-a-real-method']]);
        CommandHelper::configureDownloadBuildInstallOptions($command);
        CommandHelper::validateInput($input, $command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value "not-a-real-method" for --suppress-download-url-method; valid values are: composer-default, windows-binary, pre-packaged-source, pre-packaged-binary');
        CommandHelper::determineSuppressedDownloadUrlMethods($input);
    }

    public function testListRepositories(): void
    {
        $io = new BufferIO();

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

        CommandHelper::listRepositories($composer, $io);

        self::assertSame(
            str_replace("\r\n", "\n", <<<'OUTPUT'
            The following repositories are in use for this Target PHP:
              - Packagist
              - Composer (https://repo.packagist.com/example)
              - VCS Repository (https://github.com/php/pie)
              - Path Repository (/path/to/repo)
            OUTPUT),
            str_replace("\r\n", "\n", trim($io->getOutput())),
        );
    }
}
