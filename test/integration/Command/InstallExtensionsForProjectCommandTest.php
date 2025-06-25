<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Composer\Composer;
use Composer\Package\Link;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\RepositoryManager;
use Composer\Semver\Constraint\Constraint;
use Php\Pie\Command\InstallExtensionsForProjectCommand;
use Php\Pie\ComposerIntegration\MinimalHelperSet;
use Php\Pie\ComposerIntegration\PieJsonEditor;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\InstallForPhpProject\ComposerFactoryForProject;
use Php\Pie\Installing\InstallForPhpProject\DetermineExtensionsRequired;
use Php\Pie\Installing\InstallForPhpProject\FindMatchingPackages;
use Php\Pie\Installing\InstallForPhpProject\InstallPiePackageFromPath;
use Php\Pie\Installing\InstallForPhpProject\InstallSelectedPackage;
use Php\Pie\Platform\InstalledPiePackages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

use function getcwd;

#[CoversClass(InstallExtensionsForProjectCommand::class)]
final class InstallExtensionsForProjectCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private ComposerFactoryForProject&MockObject $composerFactoryForProject;
    private FindMatchingPackages&MockObject $findMatchingPackages;
    private InstalledPiePackages&MockObject $installedPiePackages;
    private InstallSelectedPackage&MockObject $installSelectedPackage;
    private QuestionHelper&MockObject $questionHelper;
    private InstallPiePackageFromPath&MockObject $installPiePackage;

    public function setUp(): void
    {
        parent::setUp();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            /** @param class-string $service */
            function (string $service): mixed {
                switch ($service) {
                    case QuieterConsoleIO::class:
                        return new QuieterConsoleIO(
                            new ArrayInput([]),
                            new BufferedOutput(),
                            new MinimalHelperSet(['question' => new QuestionHelper()]),
                        );

                    default:
                        return $this->createMock($service);
                }
            },
        );

        $this->composerFactoryForProject = $this->createMock(ComposerFactoryForProject::class);
        $this->findMatchingPackages      = $this->createMock(FindMatchingPackages::class);
        $this->installedPiePackages      = $this->createMock(InstalledPiePackages::class);
        $this->installSelectedPackage    = $this->createMock(InstallSelectedPackage::class);
        $this->installPiePackage         = $this->createMock(InstallPiePackageFromPath::class);
        $this->questionHelper            = $this->createMock(QuestionHelper::class);

        $cmd = new InstallExtensionsForProjectCommand(
            $this->composerFactoryForProject,
            new DetermineExtensionsRequired(),
            $this->installedPiePackages,
            $this->findMatchingPackages,
            $this->installSelectedPackage,
            $this->installPiePackage,
            $container,
        );
        $cmd->setHelperSet(new HelperSet([
            'question' => $this->questionHelper,
        ]));
        $this->commandTester = new CommandTester($cmd);
    }

    public function testInstallingExtensionsForPhpProject(): void
    {
        $rootPackage = new RootPackage('my/project', '1.2.3.0', '1.2.3');
        $rootPackage->setRequires([
            'ext-standard' => new Link('my/project', 'ext-standard', new Constraint('=', '*'), Link::TYPE_REQUIRE, '*'),
            'ext-foobar' => new Link('my/project', 'ext-foobar', new Constraint('=', '*'), Link::TYPE_REQUIRE, '*'),
//            'ext-mismatching' => new Link('my/project', 'ext-mismatching', new MultiConstraint([
//                new Constraint('>=', '2.0.0.0-dev'),
//                new Constraint('<', '3.0.0.0-dev'),
//            ]), Link::TYPE_REQUIRE, '^2.0'),
        ]);
        $this->composerFactoryForProject->method('rootPackage')->willReturn($rootPackage);

        $installedRepository = new InstalledArrayRepository([$rootPackage]);

        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager->method('getLocalRepository')->willReturn($installedRepository);

        $composer = $this->createMock(Composer::class);
        $composer->method('getPackage')->willReturn($rootPackage);
        $composer->method('getRepositoryManager')->willReturn($repositoryManager);

        $this->composerFactoryForProject->method('composer')->willReturn($composer);

//        $this->installedPiePackages->method('allPiePackages')->willReturn([
//            'mismatching' => new Package(
//                $this->createMock(CompletePackageInterface::class),
//                ExtensionType::PhpModule,
//                ExtensionName::normaliseFromString('mismatching'),
//                'vendor/mismatching',
//                '1.9.3',
//                null,
//            ),
//        ]);

        $this->findMatchingPackages->method('for')->willReturn([
            ['name' => 'vendor1/foobar', 'description' => 'The official foobar implementation'],
            ['name' => 'vendor2/afoobar', 'description' => 'An improved async foobar extension'],
        ]);

        $this->questionHelper->method('ask')->willReturn('vendor1/foobar: The official foobar implementation');

        $this->installSelectedPackage->expects(self::once())
            ->method('withPieCli')
            ->with('vendor1/foobar');

        $this->commandTester->execute(
            [],
            ['verbosity' => BufferedOutput::VERBOSITY_VERY_VERBOSE],
        );

        $outputString = $this->commandTester->getDisplay();

        $this->commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Checking extensions for your project my/project', $outputString);
        self::assertStringContainsString('requires: ext-standard:* ✅ Already installed', $outputString);
        self::assertStringContainsString('requires: ext-foobar:* 🚫 Missing', $outputString);
    }

    public function testInstallingExtensionsForPieProject(): void
    {
        $rootPackage = new RootPackage('my/project', '1.2.3.0', '1.2.3');
        $rootPackage->setType(ExtensionType::PhpModule->value);
        $this->composerFactoryForProject->method('rootPackage')->willReturn($rootPackage);

        $this->installPiePackage
            ->expects(self::once())
            ->method('__invoke')
            ->with(
                self::isInstanceOf(InstallExtensionsForProjectCommand::class),
                getcwd(),
                $rootPackage,
                self::isInstanceOf(PieJsonEditor::class),
                self::isInstanceOf(InputInterface::class),
                self::isInstanceOf(OutputInterface::class),
            )
            ->willReturn(Command::SUCCESS);

        $this->commandTester->execute(
            [],
            ['verbosity' => BufferedOutput::VERBOSITY_VERY_VERBOSE],
        );

        $this->commandTester->assertCommandIsSuccessful();
    }
}
