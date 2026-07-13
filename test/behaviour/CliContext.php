<?php

declare(strict_types=1);

namespace Php\PieBehaviourTest;

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Composer\Semver\VersionParser;
use Composer\Util\Platform;
use RuntimeException;
use Safe\Exceptions\PcreException;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function array_combine;
use function array_map;
use function array_merge;
use function preg_quote;
use function Safe\copy;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\preg_match;
use function Safe\preg_match_all;
use function Safe\realpath;
use function sprintf;
use function str_contains;
use function str_replace;
use function trim;

class CliContext implements Context
{
    private const PHP_BINARY         = 'php';
    private const PIE_BINARY         = '/usr/local/bin/pie';
    private const PIE_BINARY_BACKUP  = '/usr/local/bin/pie.original';
    private string|null $output      = null;
    private string|null $errorOutput = null;
    private int|null $exitCode       = null;
    /** @var list<string> */
    private array $phpArguments = [];
    /** @var list<array{extension: string, package: non-empty-string}> */
    private array $interactions           = [];
    private string|null $workingDirectory = null;
    private string $pieJsonFilename;
    private string $pieLockFilename;
    private string $pieJsonContentBackup;
    private string $pieLockContentBackup;

    /** @throws PcreException */
    #[AfterScenario]
    public function removeInstalledExtensions(): void
    {
        $this->runPieCommand(['show']);
        if (! preg_match_all('#([a-zA-Z0-9-_]+/[a-zA-Z0-9-_]+):#', (string) $this->output, $installedExtensionPackageNames)) {
            return;
        }

        foreach ($installedExtensionPackageNames[1] as $extensionPackageName) {
            if ($extensionPackageName === 'xdebug/xdebug') {
                continue;
            }

            $this->runPieCommand(['uninstall', $extensionPackageName]);
        }
    }

    #[When('I run a command to download the latest version of an extension')]
    #[Given('an extension was previously downloaded but not built')]
    public function iRunACommandToDownloadTheLatestVersionOfAnExtension(): void
    {
        $this->interactions[] = ['extension' => 'example_pie_extension', 'package' => 'asgrim/example-pie-extension'];
        $this->runPieCommand(['download', 'asgrim/example-pie-extension']);
    }

    #[When('I run a command to download multiple extensions')]
    public function iRunACommandToDownloadMultipleExtensions(): void
    {
        $this->interactions[] = ['extension' => 'example_pie_extension', 'package' => 'asgrim/example-pie-extension'];
        $this->interactions[] = ['extension' => 'quickhash', 'package' => 'derickr/quickhash'];
        $this->runPieCommand(['download', 'asgrim/example-pie-extension', 'derickr/quickhash']);
    }

    #[When('I run a command to download version :version of an extension')]
    public function iRunACommandToDownloadSpecificVersionOfAnExtension(string $version): void
    {
        $this->interactions[] = ['extension' => 'example_pie_extension', 'package' => 'asgrim/example-pie-extension'];
        $this->runPieCommand(['download', 'asgrim/example-pie-extension:' . $version]);
    }

    /** @param list<non-empty-string> $command */
    public function runPieCommand(array $command): void
    {
        $pieCommand = array_merge([self::PHP_BINARY, ...$this->phpArguments, self::PIE_BINARY], $command);

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
    #[Then('the extensions should have been downloaded')]
    public function theLatestVersionShouldHaveBeenDownloaded(): void
    {
        $this->assertCommandSuccessful();

        foreach ($this->interactions as $downloads) {
            Assert::regex($this->output, '#Found package: ' . $downloads['package'] . ':v?\d+\.\d+\.\d+ which provides ext-' . $downloads['extension'] . '#');
            Assert::regex($this->output, '#Extracted ' . $downloads['package'] . ':v?\d+\.\d+\.\d+ source to: #');
        }
    }

    #[Then('version :version should have been downloaded')]
    public function versionOfTheExtensionShouldHaveBeen(string $version): void
    {
        $this->assertCommandSuccessful();

        foreach ($this->interactions as $downloads) {
            Assert::contains($this->output, 'Found package: ' . $downloads['package'] . ':' . $version);
        }
    }

    #[When('I run a command to build an extension')]
    #[Given('an extension was previously built but not installed')]
    public function iRunACommandToBuildAnExtension(): void
    {
        $this->runPieCommand(['build', 'asgrim/example-pie-extension']);
    }

    #[When('I run a command to build multiple extensions')]
    public function iRunACommandToBuildMultipleExtensions(): void
    {
        $this->runPieCommand(['build', 'asgrim/example-pie-extension', 'derickr/quickhash']);
    }

    #[Then('the extension should have been built')]
    #[Then('the extensions should have been built')]
    public function theExtensionShouldHaveBeenBuilt(): void
    {
        $this->assertCommandSuccessful();

        if (Platform::isWindows()) {
            Assert::contains($this->output, 'Nothing to do on Windows');

            return;
        }

        if (str_contains($this->output, 'Found prebuilt archive')) {
            Assert::contains($this->output, 'Found prebuilt archive');
            Assert::contains($this->output, 'Pre-packaged binary found');

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

        if (str_contains($this->output, 'Found prebuilt archive')) {
            Assert::contains($this->output, 'Found prebuilt archive');
            Assert::contains($this->output, 'Pre-packaged binary found');

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
        $this->interactions[] = ['extension' => 'example_pie_extension', 'package' => 'asgrim/example-pie-extension'];
        $this->runPieCommand(['install', 'asgrim/example-pie-extension']);
    }

    #[When('I run a command to install multiple extensions')]
    #[Given('multiple extensions were previously installed and enabled')]
    public function iRunACommandToInstallMultipleExtensions(): void
    {
        $this->interactions[] = ['extension' => 'example_pie_extension', 'package' => 'asgrim/example-pie-extension'];
        $this->interactions[] = ['extension' => 'quickhash', 'package' => 'derickr/quickhash'];
        $this->runPieCommand(['install', 'asgrim/example-pie-extension', 'derickr/quickhash']);
    }

    #[When('I run a command to forcefully install an extension')]
    public function iRunACommandToForcefullyInstallAnExtension(): void
    {
        $this->interactions[] = ['extension' => 'example_pie_extension', 'package' => 'asgrim/example-pie-extension'];
        $this->runPieCommand(['install', '--force', 'asgrim/example-pie-extension']);
    }

    #[When('I run a command to install an extension without enabling it')]
    public function iRunACommandToInstallAnExtensionWithoutEnabling(): void
    {
        $this->interactions[] = ['extension' => 'example_pie_extension', 'package' => 'asgrim/example-pie-extension'];
        $this->runPieCommand(['install', 'asgrim/example-pie-extension', '--skip-enable-extension']);
    }

    #[When('I run a command to uninstall an extension')]
    public function iRunACommandToUninstallAnExtension(): void
    {
        $this->runPieCommand(['uninstall', ...array_map(static fn (array $interaction) => $interaction['package'], $this->interactions)]);
    }

    #[When('I run a command to uninstall multiple extensions')]
    public function iRunACommandToUninstallMultipleExtensions(): void
    {
        $this->interactions[] = ['extension' => 'example_pie_extension', 'package' => 'asgrim/example-pie-extension'];
        $this->interactions[] = ['extension' => 'quickhash', 'package' => 'derickr/quickhash'];
        $this->runPieCommand(['uninstall', 'asgrim/example-pie-extension', 'derickr/quickhash']);
    }

    #[Then('the extension should not be installed anymore')]
    #[Then('the extensions should not be installed anymore')]
    public function theExtensionShouldNotBeInstalled(): void
    {
        $this->assertCommandSuccessful();

        foreach ($this->interactions as $uninstall) {
            if (Platform::isWindows()) {
                Assert::regex($this->output, '#👋 Removed extension ' . preg_quote($uninstall['package'], '#') . ':[^:]+: [-\\\_:.a-zA-Z0-9]+\\\php_' . preg_quote($uninstall['extension'], '#') . '.dll#');
            } else {
                Assert::regex($this->output, '#👋 Removed extension ' . preg_quote($uninstall['package'], '#') . ':[^:]+: [-_.a-zA-Z0-9/]+/' . preg_quote($uninstall['extension'], '#') . '.so#');
            }

            $isExtEnabled = (new Process([self::PHP_BINARY, '-r', 'echo extension_loaded("' . $uninstall['extension'] . '")?"yes":"no";']))
                ->mustRun()
                ->getOutput();

            Assert::same(
                $isExtEnabled,
                'no',
                sprintf("Failed to remove extension.\n\nOutput:\n%s\n\nError output:\n%s\n", $this->output, $this->errorOutput),
            );
        }
    }

    #[Then('the extension should have been installed')]
    public function theExtensionShouldHaveBeenInstalled(): void
    {
        $this->assertCommandSuccessful();

        Assert::contains($this->output, 'Extension has NOT been automatically enabled.');

        foreach ($this->interactions as $install) {
            if (Platform::isWindows()) {
                Assert::regex($this->output, '#Copied DLL to: [-\\\_:.a-zA-Z0-9]+\\\php_' . $install['extension'] . '.dll#');

                continue;
            }

            Assert::regex($this->output, '#Install complete: [-_.a-zA-Z0-9/]+/' . $install['extension'] . '.so#');
        }
    }

    #[Then('the extension should have been installed and enabled')]
    #[Then('the extensions should have been installed and enabled')]
    public function theExtensionShouldHaveBeenInstalledAndEnabled(): void
    {
        $this->assertCommandSuccessful();

        foreach ($this->interactions as $install) {
            Assert::regex($this->output, '#Extension ' . preg_quote($install['package'], '#') . ':\S+ is enabled and loaded#');

            if (Platform::isWindows()) {
                Assert::regex($this->output, '#Copied DLL to: [-\\\_:.a-zA-Z0-9]+\\\php_' . preg_quote($install['extension'], '#') . '.dll#');

                continue;
            }

            Assert::regex($this->output, '#Install complete: [-_.a-zA-Z0-9/]+/' . preg_quote($install['extension'], '#') . '.so#');

            $isExtEnabled = (new Process([self::PHP_BINARY, '-r', 'echo extension_loaded("' . $install['extension'] . '")?"yes":"no";']))
                ->mustRun()
                ->getOutput();

            Assert::same($isExtEnabled, 'yes');
        }
    }

    #[Then('the extension should not have been re-installed')]
    public function theExtensionShouldNotHaveBeenReinstalled(): void
    {
        $this->assertCommandSuccessful();

        foreach ($this->interactions as $noops) {
            Assert::contains($this->output, 'PIE package ' . $noops['package'] . ' (' . $noops['extension'] . ') is already installed and verified.');

            $isExtEnabled = (new Process([self::PHP_BINARY, '-r', 'echo extension_loaded("' . $noops['extension'] . '")?"yes":"no";']))
                ->mustRun()
                ->getOutput();

            Assert::same($isExtEnabled, 'yes');
        }
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
        $this->interactions[] = ['extension' => 'sodium', 'package' => 'php/sodium'];
        $this->runPieCommand(['install', 'php/sodium']);
    }

    #[Given('I do not have libsodium on my system')]
    public function iDoNotHaveLibsodiumOnMySystem(): void
    {
        (new Process(['apt-get', '-y', '-m', 'remove', 'libsodium*'], timeout: 120))->run();
    }

    #[When('I display information about the sodium extension with PIE')]
    public function iDisplayInformationAboutTheSodiumExtensionWithPie(): void
    {
        $this->interactions[] = ['extension' => 'sodium', 'package' => 'php/sodium'];
        $this->runPieCommand(['info', 'php/sodium']);
    }

    #[Then('the information should show that libsodium is a missing dependency')]
    public function theInformationShouldShowThatLibsodiumIsAMissingDependency(): void
    {
        Assert::notNull($this->output);
        Assert::contains(
            $this->output,
            'lib-sodium: * 🚫 (not installed)',
            sprintf("Could not find missing lib-sodium.\n\nOutput:\n%s\n\nError output:\n%s\n", $this->output, $this->errorOutput),
        );
    }

    #[Then('the extension fails to install due to the missing library')]
    public function theExtensionFailsToInstallDueToTheMissingLibrary(): void
    {
        Assert::notSame(0, $this->exitCode);
        Assert::notNull($this->errorOutput);
        Assert::regex(
            $this->errorOutput,
            '#Cannot use php/sodium\'s latest version .* as it requires lib-sodium .* which is missing from your platform.#',
            sprintf("Did not detect missing lib-sodium correctly.\n\nOutput:\n%s\n\nError output:\n%s\n", $this->output, $this->errorOutput),
        );
    }

    #[Given('I am in a PHP project that has missing extensions')]
    public function iAmInAPHPProjectThatHasMissingExtensions(): void
    {
        $this->runPieCommand(['uninstall', 'asgrim/example-pie-extension']);

        $this->runPieCommand(['show']);
        $this->assertCommandSuccessful();
        Assert::notContains($this->output, 'example_pie_extension');

        $this->workingDirectory = realpath(__DIR__ . '/../assets/example-php-project');
    }

    #[When('I run a command to install the extensions with package selections')]
    public function iRunACommandToInstallTheExtensions(): void
    {
        $this->runPieCommand([
            'install',
            '--select',
            'example_pie_extension=asgrim/example-pie-extension',
            '--select',
            'redis=phpredis/phpredis',
        ]);
    }

    #[Then('I should see all the extensions are now installed')]
    public function iShouldSeeAllTheExtensionsAreNowInstalled(): void
    {
        $this->workingDirectory = null;
        $this->assertCommandSuccessful();

        $this->runPieCommand(['show']);
        $this->assertCommandSuccessful();
        $pieShowOutput = $this->output;

        self::assertPackageVersionInstalledInPieShowOutput($pieShowOutput, 'asgrim/example-pie-extension');
        self::assertPackageVersionInstalledInPieShowOutput($pieShowOutput, 'phpredis/phpredis');
    }

    #[Then('I should see information on how to select packages for install')]
    public function iShouldSeeInformationOnHowToSelectPackagesForInstall(): void
    {
        Assert::same($this->exitCode, 1);

        Assert::notNull($this->errorOutput);
        Assert::contains($this->errorOutput, 'No package selections were made for ext-redis; you MUST specify a package selection in non-interactive mode');
        Assert::contains($this->errorOutput, '--select=example_pie_extension=asgrim/example-pie-extension');
        Assert::contains($this->errorOutput, '--select=redis=phpredis/phpredis');
    }

    #[Given('I am in a PIE project')]
    public function iAmInAPIEProject(): void
    {
        $this->workingDirectory = realpath('/example-pie-extension');
    }

    #[When('I run a command to install the extension')]
    #[When('I run a command to install the extensions without package selections')]
    public function iRunACommandToInstallTheExtension(): void
    {
        // Note: implied from composer.json, we don't explicitly request the packages here
        $this->interactions[] = ['extension' => 'example_pie_extension', 'package' => 'asgrim/example-pie-extension'];
        $this->runPieCommand(['install']);
    }

    #[Given('I have an old version of PIE')]
    public function iHaveAnOldVersionOfPIE(): void
    {
        // noop
    }

    #[When('I update PIE to the latest version')]
    public function iUpdatePIEToTheLatestNightlyVersion(): void
    {
        $this->runPieCommand(['self-update', '--nightly', '-v']);

        copy(self::PIE_BINARY_BACKUP, self::PIE_BINARY);
    }

    #[Then('I should see I have been updated to the latest version')]
    public function iShouldSeeIHaveBeenUpdatedToTheLatestVersion(): void
    {
        $this->assertCommandSuccessful();
        Assert::contains($this->output, '✅ Verified the new PIE version');
        Assert::contains($this->output, '✅ PIE has been upgraded to nightly');
    }

    #[Given('I have a pie.phar built on a nasty hacker\'s machine')]
    public function iHaveAPiePharBuiltOnANastyHackerSMachine(): void
    {
        // noop - the pie.phar built in this does not have attestations
    }

    #[When('I verify my PIE installation')]
    public function iVerifyMyPIEInstallation(): void
    {
        $this->runPieCommand(['self-verify']);
    }

    #[Then('I should see it has failed verification')]
    public function iShouldSeeItHasFailedVerification(): void
    {
        Assert::same($this->exitCode, 1);

        Assert::notNull($this->errorOutput);
        Assert::contains($this->errorOutput, '❌ Failed to verify that this PIE binary is the authentic release');
    }

    private function copyPieJsonAndLock(string $asset): void
    {
        // Find existing pie.json
        $this->runPieCommand(['show', '-v']);
        Assert::notNull($this->output);
        preg_match('#Using pie\.json: (.+)#', $this->output, $pieJsonRegexMatches);
        Assert::keyExists($pieJsonRegexMatches, 1);
        $this->pieJsonFilename = $pieJsonRegexMatches[1];
        $this->pieLockFilename = str_replace('pie.json', 'pie.lock', $this->pieJsonFilename);

        // Make a backup of the pie.json/.lock
        $this->pieJsonContentBackup = file_get_contents($this->pieJsonFilename);
        $this->pieLockContentBackup = file_get_contents($this->pieLockFilename);

        // Copy the new ones over
        $testPieLockPath = realpath(__DIR__ . '/../assets/' . $asset);
        copy($testPieLockPath . '/pie.json', $this->pieJsonFilename);
        copy($testPieLockPath . '/pie.lock', $this->pieLockFilename);
    }

    private function restorePieJsonAndLock(): void
    {
        file_put_contents($this->pieJsonFilename, $this->pieJsonContentBackup);
        file_put_contents($this->pieLockFilename, $this->pieLockContentBackup);
        $this->runPieCommand(['install', '--from-lock']);
    }

    #[Given('I have installed PIE extensions that have upgrades available')]
    public function iHaveInstalledPieExtensionsThatHaveUpgradesAvailable(): void
    {
        $this->runPieCommand(['install', 'asgrim/example-pie-extension:2.0.7']);
        $this->copyPieJsonAndLock('pie-upgrade-lock');
    }

    #[Given('I have installed PIE extensions with configure options that have upgrades available')]
    public function iHaveInstalledPieExtensionsThatHaveConfigureOptions(): void
    {
        $this->runPieCommand(['install', 'asgrim/example-pie-extension:2.0.7', '--with-hello-name=UpgradeTest']);
        $this->copyPieJsonAndLock('pie-upgrade-lock');
    }

    #[Given('I have a lock file')]
    public function iHaveALockfile(): void
    {
        $this->runPieCommand(['install', 'xdebug/xdebug:3.5.3', 'derickr/quickhash']);
        $this->copyPieJsonAndLock('pie-install-from-lock');
    }

    #[When('I run a command to install from the lockfile')]
    public function iRunACommandToInstallFromTheLockfile(): void
    {
        $this->runPieCommand(['install', '-v', '--from-lock']);
    }

    #[When('I run a command to upgrade my extensions')]
    public function iRunACommandToUpgradeMyExtensions(): void
    {
        $this->runPieCommand(['upgrade', '-v']);
    }

    /** @return array<string, string> */
    private static function installedExtensionPackagesAndVersions(string $pieShowOutput): array
    {
        if (! preg_match_all('#([a-zA-Z0-9-_]+/[a-zA-Z0-9-_]+):([^ ]+)#', $pieShowOutput, $matches)) {
            throw new RuntimeException('no packages found in pie show');
        }

        return array_combine($matches[1], $matches[2]);
    }

    private static function assertPackageVersionInstalledInPieShowOutput(string $pieShowOutput, string $expectedPackage, string|null $expectedVersion = null): void
    {
        $installedExtensionPackagesAndVersions = self::installedExtensionPackagesAndVersions($pieShowOutput);
        Assert::keyExists($installedExtensionPackagesAndVersions, $expectedPackage);

        if ($expectedVersion === null) {
            return;
        }

        $versionParser       = new VersionParser();
        $installedConstraint = $versionParser->parseConstraints($installedExtensionPackagesAndVersions[$expectedPackage]);
        $expectedConstraint  = $versionParser->parseConstraints($expectedVersion);
        Assert::true(
            $expectedConstraint->matches($installedConstraint),
            sprintf(
                'Installed version %s does not match expected constraint %s',
                $installedConstraint->getPrettyString(),
                $expectedConstraint->getPrettyString(),
            ),
        );
    }

    private static function assertPackageNotInstalledInPieShowOutput(string $pieShowOutput, string $notExpectedPackage): void
    {
        Assert::keyNotExists(
            self::installedExtensionPackagesAndVersions($pieShowOutput),
            $notExpectedPackage,
        );
    }

    #[Then('the extensions should have been updated to the lock')]
    public function theExtensionsShouldHaveBeenUpdatedToTheLock(): void
    {
        $this->assertCommandSuccessful();

        Assert::notNull($this->output);
        $pieInstallOutput = $this->output;

        $this->runPieCommand(['show']);
        $this->assertCommandSuccessful();
        $pieShowOutput = $this->output;

        // `xdebug` should be downgraded to 3.5.2
        Assert::contains($pieInstallOutput, 'PIE package xdebug/xdebug (xdebug) is at 3.5.3 but the lock requires 3.5.2, scheduling for reinstall');
        Assert::contains($pieInstallOutput, 'Extension xdebug/xdebug:3.5.2 is enabled and loaded');
        self::assertPackageVersionInstalledInPieShowOutput($pieShowOutput, 'xdebug/xdebug', '3.5.2');

        // `quickhash` should have been removed (not in lockfile)
        Assert::contains($pieInstallOutput, 'Removed extension derickr/quickhash:');
        self::assertPackageNotInstalledInPieShowOutput($pieShowOutput, 'derickr/quickhash');

        // `example_pie_extension` 2.0.9 should have been installed (was not previously installed)
        Assert::contains($pieInstallOutput, 'Extension asgrim/example-pie-extension:2.0.9 is enabled and loaded');
        self::assertPackageVersionInstalledInPieShowOutput($pieShowOutput, 'asgrim/example-pie-extension', '2.0.9');

        $this->restorePieJsonAndLock();
    }

    #[Then('the extensions should have been upgraded to the latest versions')]
    public function theExtensionsShouldHaveBeenUpgradedToTheLatestVersions(): void
    {
        $this->runPieCommand(['show']);
        $this->assertCommandSuccessful();
        $pieShowOutput = $this->output;

        // xdebug/xdebug should still exist (upgrade should NOT uninstall it)
        self::assertPackageVersionInstalledInPieShowOutput($pieShowOutput, 'xdebug/xdebug');

        // asgrim/example-pie-extension should be newer than 2.0.7 (min 2.0.9 at time of writing)
        self::assertPackageVersionInstalledInPieShowOutput($pieShowOutput, 'asgrim/example-pie-extension', '^2.0.9');

        $this->restorePieJsonAndLock();
    }

    #[Then('the extension has been upgraded with the previous configure options')]
    public function theExtensionsShouldHaveBeenUpgradedWithThePreviousConfigureOptions(): void
    {
        $this->runPieCommand(['show']);
        $this->assertCommandSuccessful();
        $pieShowOutput = $this->output;

        // xdebug/xdebug should still exist (upgrade should NOT uninstall it)
        self::assertPackageVersionInstalledInPieShowOutput($pieShowOutput, 'xdebug/xdebug');

        // asgrim/example-pie-extension should be newer than 2.0.7 (min 2.0.9 at time of writing)
        self::assertPackageVersionInstalledInPieShowOutput($pieShowOutput, 'asgrim/example-pie-extension', '^2.0.9');

        $exampleTest = (new Process([self::PHP_BINARY, '-r', 'example_pie_extension_test();']))
            ->mustRun()
            ->getOutput();

        Assert::same(trim($exampleTest), 'Hello, UpgradeTest!');

        $this->restorePieJsonAndLock();
    }
}
