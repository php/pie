<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Command;

use Composer\Util\Platform;
use InvalidArgumentException;
use Php\Pie\Command\CommandHelper;
use Php\Pie\ConfigureOption;
use Php\Pie\DependencyResolver\DependencyResolver;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadAndExtract;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function array_combine;
use function array_map;
use function escapeshellarg;

#[CoversClass(CommandHelper::class)]
final class CommandHelperTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
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

    #[DataProvider('validPackageAndVersions')]
    public function testRequestedNameAndVersionPair(string $requestedPackageAndVersion, string $expectedPackage, string|null $expectedVersion): void
    {
        $input = $this->createMock(InputInterface::class);

        $input->expects(self::once())
            ->method('getArgument')
            ->with('requested-package-and-version')
            ->willReturn($requestedPackageAndVersion);

        self::assertSame(
            [
                'name' => $expectedPackage,
                'version' => $expectedVersion,
            ],
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

    public function testDownloadPackage(): void
    {
        $dependencyResolver          = $this->createMock(DependencyResolver::class);
        $targetPlatform              = TargetPlatform::fromPhpBinaryPath(PhpBinaryPath::fromCurrentProcess());
        $requestedNameAndVersionPair = ['name' => 'php/test-pie-ext', 'version' => '^1.2'];
        $downloadAndExtract          = $this->createMock(DownloadAndExtract::class);
        $output                      = $this->createMock(OutputInterface::class);

        $dependencyResolver->expects(self::once())
            ->method('__invoke')
            ->with(
                $targetPlatform,
            )
            ->willReturn($package = new Package(
                ExtensionType::PhpModule,
                ExtensionName::normaliseFromString('test_pie_ext'),
                'php/test-pie-ext',
                '1.2.3',
                'https://test-uri/',
                [],
            ));

        $downloadAndExtract->expects(self::once())
            ->method('__invoke')
            ->with(
                $targetPlatform,
                $package,
            )
            ->willReturn($downloadedPackage = DownloadedPackage::fromPackageAndExtractedPath(
                $package,
                '/foo/bar',
            ));

        $output->expects(self::once())
            ->method('writeln')
            ->with(self::stringContains('<info>Found package:</info> php/test-pie-ext:1.2.3 which provides <info>ext-test_pie_ext</info>'));

        self::assertSame($downloadedPackage, CommandHelper::downloadPackage(
            $dependencyResolver,
            $targetPlatform,
            $requestedNameAndVersionPair,
            $downloadAndExtract,
            $output,
        ));
    }

    public function testBindingConfigurationOptionsFromPackage(): void
    {
        self::markTestIncomplete(__METHOD__);
    }

    public function testProcessingConfigureOptionsFromInput(): void
    {
        $package         = new Package(
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('lolz'),
            'foo/bar',
            '1.0.0',
            null,
            [
                ConfigureOption::fromComposerJsonDefinition([
                    'name' => 'with-stuff',
                    'needs-value' => true,
                ]),
                ConfigureOption::fromComposerJsonDefinition(['name' => 'enable-thing']),
            ],
        );
        $inputDefinition = new InputDefinition();
        $inputDefinition->addOption(new InputOption('with-stuff', null, InputOption::VALUE_REQUIRED));
        $inputDefinition->addOption(new InputOption('enable-thing', null, InputOption::VALUE_NONE));

        $input = new ArrayInput(['--with-stuff' => 'lolz', '--enable-thing' => true], $inputDefinition);

        $options = CommandHelper::processConfigureOptionsFromInput($package, $input);

        self::assertSame(
            [
                '--with-stuff=' . escapeshellarg('lolz'),
                '--enable-thing',
            ],
            $options,
        );
    }

    public function testWindowsMachinesCannotUseWithPhpConfigOption(): void
    {
        if (! Platform::isWindows()) {
            self::markTestSkipped('This test can only run on Windows');
        }

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
}
