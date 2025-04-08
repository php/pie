<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Composer\Package\Link;
use Composer\Package\RootPackage;
use Composer\Semver\Constraint\Constraint;
use Php\Pie\Command\InstallExtensionsForProjectCommand;
use Php\Pie\ComposerIntegration\InstallAndBuildProcess;
use Php\Pie\ComposerIntegration\MinimalHelperSet;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\Installing\InstallForPhpProject\FindMatchingPackages;
use Php\Pie\Installing\InstallForPhpProject\FindRootPackage;
use Php\Pie\Installing\InstallForPhpProject\InstallSelectedPackage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(InstallExtensionsForProjectCommand::class)]
final class InstallExtensionsForProjectCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private FindRootPackage&MockObject $findRootpackage;
    private FindMatchingPackages&MockObject $findMatchingPackages;
    private InstallSelectedPackage&MockObject $installSelectedPackage;
    private QuestionHelper&MockObject $questionHelper;

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

        $this->findRootpackage        = $this->createMock(FindRootPackage::class);
        $this->findMatchingPackages   = $this->createMock(FindMatchingPackages::class);
        $this->installSelectedPackage = $this->createMock(InstallSelectedPackage::class);
        $this->questionHelper         = $this->createMock(QuestionHelper::class);

        $cmd = new InstallExtensionsForProjectCommand(
            $this->findRootpackage,
            $this->findMatchingPackages,
            $this->installSelectedPackage,
            $container,
        );
        $cmd->setHelperSet(new HelperSet([
            'question' => $this->questionHelper,
        ]));
        $this->commandTester = new CommandTester($cmd);
    }

    public function testInstallingExtensionsForProject(): void
    {
        $rootPackage = new RootPackage('my/project', '1.2.3.0', '1.2.3');
        $rootPackage->setRequires([
            new Link('my/project', 'ext-standard', new Constraint('=', '*'), Link::TYPE_REQUIRE),
            new Link('my/project', 'ext-foobar', new Constraint('=', '*'), Link::TYPE_REQUIRE),
        ]);
        $this->findRootpackage->method('forCwd')->willReturn($rootPackage);

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
        self::assertStringContainsString('requires: standard ✅ Already installed', $outputString);
        self::assertStringContainsString('requires: foobar ⚠️  Missing', $outputString);
    }
}
