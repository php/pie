<?php

declare(strict_types=1);

namespace Php\PieBehaviourTest;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Composer\Util\Platform;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function array_merge;
use function assert;
use function realpath;
use function sprintf;

class CliContext implements Context
{
    private const PHP_BINARY         = 'php';
    private string|null $output      = null;
    private string|null $errorOutput = null;
    private int|null $exitCode       = null;
    /** @var list<string> */
    private array $phpArguments           = [];
    private string $theExtension          = 'example_pie_extension';
    private string $thePackage            = 'asgrim/example-pie-extension';
    private string|null $workingDirectory = null;

    #[When('I run a command to download the latest version of an extension')]
    public function iRunACommandToDownloadTheLatestVersionOfAnExtension(): void
    {
        $this->runPieCommand(['download', 'asgrim/example-pie-extension']);
    }

    #[When('I run a command to download version :version of an extension')]
    public function iRunACommandToDownloadSpecificVersionOfAnExtension(string $version): void
    {
        $this->runPieCommand(['download', 'asgrim/example-pie-extension:' . $version]);
    }

    /** @param list<non-empty-string> $command */
    public function runPieCommand(array $command): void
    {
        $pieCommand = array_merge([self::PHP_BINARY, ...$this->phpArguments, 'bin/pie'], $command);

        if ($this->workingDirectory !== null) {
            $pieCommand[] = '--working-dir';
            $pieCommand[] = $this->workingDirectory;
        }

        $proc = new Process($pieCommand, timeout: 120);
        $proc->run();

        $this->output      = $proc->getOutput();
        $this->errorOutput = $proc->getErrorOutput();
        $this->exitCode    = $proc->getExitCode();
    }

    /** @phpstan-assert !null $this->output */
    private function assertCommandSuccessful(): void
    {
        Assert::same(
            0,
            $this->exitCode,
            sprintf(
                <<<'EOF'
                Last command was not successful - exit code was: %d.

                Output:
                %s

                Error output:
                %s
                EOF,
                $this->exitCode,
                $this->output,
                $this->errorOutput,
            ),
        );

        Assert::notNull($this->output);
    }

    #[Then('the latest version should have been downloaded')]
    public function theLatestVersionShouldHaveBeenDownloaded(): void
    {
        $this->assertCommandSuccessful();
        Assert::regex($this->output, '#Found package: asgrim/example-pie-extension:v?\d+\.\d+\.\d+ which provides ext-example_pie_extension#');
        Assert::regex($this->output, '#Extracted asgrim/example-pie-extension:v?\d+\.\d+\.\d+ source to: #');
    }

    #[Then('version :version should have been downloaded')]
    public function versionOfTheExtensionShouldHaveBeen(string $version): void
    {
        $this->assertCommandSuccessful();
        Assert::contains($this->output, 'Found package: asgrim/example-pie-extension:' . $version);
    }

    #[When('I run a command to build an extension')]
    public function iRunACommandToBuildAnExtension(): void
    {
        $this->runPieCommand(['build', 'asgrim/example-pie-extension']);
    }

    #[Then('the extension should have been built')]
    public function theExtensionShouldHaveBeenBuilt(): void
    {
        $this->assertCommandSuccessful();

        if (Platform::isWindows()) {
            Assert::contains($this->output, 'Nothing to do on Windows');

            return;
        }

        Assert::contains($this->output, 'phpize complete.');
        Assert::contains($this->output, 'Configure complete');
        Assert::contains($this->output, 'Build complete:');
    }

    #[When('I run a command to build an extension with configure options')]
    public function iRunACommandToBuildAnExtensionWithConfigureOptions(): void
    {
        $this->runPieCommand(['build', 'asgrim/example-pie-extension', '--with-hello-name=sup']);
    }

    #[Then('the extension should have been built with options')]
    public function theExtensionShouldHaveBeenBuiltWithOptions(): void
    {
        $this->assertCommandSuccessful();

        if (Platform::isWindows()) {
            Assert::contains($this->output, 'Nothing to do on Windows');

            return;
        }

        Assert::contains($this->output, 'phpize complete.');
        Assert::contains($this->output, 'Configure complete with options: --with-hello-name=sup');
        Assert::contains($this->output, 'Build complete:');
    }

    #[When('I run a command to install an extension')]
    #[Given('an extension was previously installed and enabled')]
    public function iRunACommandToInstallAnExtension(): void
    {
        $this->theExtension = 'example_pie_extension';
        $this->thePackage   = 'asgrim/example-pie-extension';
        $this->runPieCommand(['install', $this->thePackage]);
    }

    #[When('I run a command to install an extension without enabling it')]
    public function iRunACommandToInstallAnExtensionWithoutEnabling(): void
    {
        $this->theExtension = 'example_pie_extension';
        $this->thePackage   = 'asgrim/example-pie-extension';
        $this->runPieCommand(['install', $this->thePackage, '--skip-enable-extension']);
    }

    #[When('I run a command to uninstall an extension')]
    public function iRunACommandToUninstallAnExtension(): void
    {
        assert($this->thePackage !== '');
        $this->runPieCommand(['uninstall', $this->thePackage]);
    }

    #[Then('the extension should not be installed anymore')]
    public function theExtensionShouldNotBeInstalled(): void
    {
        $this->assertCommandSuccessful();

        if (Platform::isWindows()) {
            Assert::regex($this->output, '#👋 Removed extension: [-\\\_:.a-zA-Z0-9]+\\\php_' . $this->theExtension . '.dll#');
        } else {
            Assert::regex($this->output, '#👋 Removed extension: [-_.a-zA-Z0-9/]+/' . $this->theExtension . '.so#');
        }

        $isExtEnabled = (new Process([self::PHP_BINARY, '-r', 'echo extension_loaded("' . $this->theExtension . '")?"yes":"no";']))
            ->mustRun()
            ->getOutput();

        Assert::same($isExtEnabled, 'no');
    }

    #[Then('the extension should have been installed')]
    public function theExtensionShouldHaveBeenInstalled(): void
    {
        $this->assertCommandSuccessful();

        Assert::contains($this->output, 'Extension has NOT been automatically enabled.');

        if (Platform::isWindows()) {
            Assert::regex($this->output, '#Copied DLL to: [-\\\_:.a-zA-Z0-9]+\\\php_' . $this->theExtension . '.dll#');

            return;
        }

        Assert::regex($this->output, '#Install complete: [-_.a-zA-Z0-9/]+/' . $this->theExtension . '.so#');
    }

    #[Then('the extension should have been installed and enabled')]
    public function theExtensionShouldHaveBeenInstalledAndEnabled(): void
    {
        $this->assertCommandSuccessful();

        Assert::contains($this->output, 'Extension is enabled and loaded');

        if (Platform::isWindows()) {
            Assert::regex($this->output, '#Copied DLL to: [-\\\_:.a-zA-Z0-9]+\\\php_' . $this->theExtension . '.dll#');

            return;
        }

        Assert::regex($this->output, '#Install complete: [-_.a-zA-Z0-9/]+/' . $this->theExtension . '.so#');

        $isExtEnabled = (new Process([self::PHP_BINARY, '-r', 'echo extension_loaded("' . $this->theExtension . '")?"yes":"no";']))
            ->mustRun()
            ->getOutput();

        Assert::same($isExtEnabled, 'yes');
    }

    #[Given('I have an invalid extension installed')]
    public function iHaveAnInvalidExtensionInstalled(): void
    {
        $this->phpArguments = ['-d', 'extension=invalid_extension'];
    }

    #[When('I add a package repository')]
    public function iAddAPackageRepository(): void
    {
        $this->runPieCommand(['repository:add', 'path', __DIR__]);
    }

    #[Then('I should see the package repository can be used by PIE')]
    public function iShouldSeeThePackageRepositoryCanBeUsedByPie(): void
    {
        Assert::notNull($this->output);
        Assert::contains($this->output, 'Path Repository (' . __DIR__ . ')');
    }

    #[Given('I have previously added a package repository')]
    public function iHavePreviouslyAddedAPackageRepository(): void
    {
        $this->noRepositoriesHavePreviouslyBeenAdded();
        $this->iAddAPackageRepository();
    }

    #[Given('no repositories have previously been added')]
    public function noRepositoriesHavePreviouslyBeenAdded(): void
    {
        $this->iRemoveThePackageRepository();
    }

    #[When('I remove the package repository')]
    public function iRemoveThePackageRepository(): void
    {
        $this->runPieCommand(['repository:remove', __DIR__]);
    }

    #[Then('I should see the package repository is not used by PIE')]
    public function iShouldSeeThePackageRepositoryIsNotUsedByPie(): void
    {
        Assert::notNull($this->output);
        Assert::notContains($this->output, 'Path repository (' . __DIR__ . ')');
    }

    #[Given('I have libsodium on my system')]
    public function iHaveLibsodiumOnMySystem(): void
    {
        (new Process(['apt-get', 'update'], timeout: 120))->mustRun();
        (new Process(['apt-get', '-y', 'install', 'libsodium-dev'], timeout: 120))->mustRun();
    }

    #[When('I install the sodium extension with PIE')]
    #[Given('I have the sodium extension installed with PIE')]
    public function iInstallTheSodiumExtensionWithPie(): void
    {
        $this->theExtension = 'sodium';
        $this->thePackage   = 'php/sodium';
        $this->runPieCommand(['install', $this->thePackage]);
    }

    #[Given('I do not have libsodium on my system')]
    public function iDoNotHaveLibsodiumOnMySystem(): void
    {
        (new Process(['apt-get', '-y', '-m', 'remove', 'libsodium*'], timeout: 120))->run();
    }

    #[When('I display information about the sodium extension with PIE')]
    public function iDisplayInformationAboutTheSodiumExtensionWithPie(): void
    {
        $this->theExtension = 'sodium';
        $this->thePackage   = 'php/sodium';
        $this->runPieCommand(['info', $this->thePackage]);
    }

    #[Then('the information should show that libsodium is a missing dependency')]
    public function theInformationShouldShowThatLibsodiumIsAMissingDependency(): void
    {
        Assert::notNull($this->output);
        Assert::contains($this->output, 'lib-sodium: * 🚫 (not installed)');
    }

    #[Then('the extension fails to install due to the missing library')]
    public function theExtensionFailsToInstallDueToTheMissingLibrary(): void
    {
        Assert::notSame(0, $this->exitCode);
        Assert::notNull($this->errorOutput);
        Assert::regex($this->errorOutput, '#Cannot use php/sodium\'s latest version .* as it requires lib-sodium .* which is missing from your platform.#');
    }

    #[Given('I am in a PHP project that has missing extensions')]
    public function iAmInAPHPProjectThatHasMissingExtensions(): void
    {
        $this->runPieCommand(['uninstall', 'asgrim/example-pie-extension']);

        $this->runPieCommand(['show']);
        $this->assertCommandSuccessful();
        Assert::notContains($this->output, 'example_pie_extension');

        $examplePhpProject = (string) realpath(__DIR__ . '/../assets/example-php-project');
        assert($examplePhpProject !== '');

        $this->workingDirectory = $examplePhpProject;
    }

    #[When('I run a command to install the extensions')]
    public function iRunACommandToInstallTheExtensions(): void
    {
        $this->runPieCommand(['install', '--allow-non-interactive-project-install']);

        $this->assertCommandSuccessful();
    }

    #[Then('I should see all the extensions are now installed')]
    public function iShouldSeeAllTheExtensionsAreNowInstalled(): void
    {
        $this->workingDirectory = null;

        $this->runPieCommand(['show']);
        $this->assertCommandSuccessful();
        Assert::contains($this->output, 'example_pie_extension');
    }
}
