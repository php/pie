<?php

declare(strict_types=1);

namespace Php\PieBehaviourTest;

use Behat\Behat\Context\Context;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function array_merge;

/** @psalm-api */
class CliContext implements Context
{
    private string|null $output = null;
    private int|null $exitCode  = null;

    /** @When I run a command to download the latest version of an extension */
    public function iRunACommandToDownloadTheLatestVersionOfAnExtension(): void
    {
        $this->runPieCommand(['download', 'asgrim/example-pie-extension']);
    }

    /** @When I run a command to download version :version of an extension */
    public function iRunACommandToDownloadSpecificVersionOfAnExtension(string $version): void
    {
        $this->runPieCommand(['download', 'asgrim/example-pie-extension:' . $version]);
    }

    /** @param list<non-empty-string> $command */
    public function runPieCommand(array $command): void
    {
        $pieCommand = array_merge(['php', 'bin/pie'], $command);

        $proc = (new Process($pieCommand))->mustRun();

        $this->output   = $proc->getOutput();
        $this->exitCode = $proc->getExitCode();
    }

    /** @psalm-assert !null $this->output */
    private function assertCommandSuccessful(): void
    {
        Assert::same(0, $this->exitCode);

        Assert::notNull($this->output);
    }

    /** @Then the latest version should have been downloaded */
    public function theLatestVersionShouldHaveBeenDownloaded(): void
    {
        $this->assertCommandSuccessful();
        Assert::regex($this->output, '#Found package: asgrim/example-pie-extension:v?\d+\.\d+\.\d+ which provides ext-example_pie_extension#');
        Assert::regex($this->output, '#Extracted asgrim/example-pie-extension:v?\d+\.\d+\.\d+ source to: #');
    }

    /** @Then version :version should have been downloaded */
    public function versionOfTheExtensionShouldHaveBeen(string $version): void
    {
        $this->assertCommandSuccessful();
        Assert::contains($this->output, 'Found package: asgrim/example-pie-extension:' . $version);
    }

    /** @When I run a command to build an extension */
    public function iRunACommandToBuildAnExtension(): void
    {
        $this->runPieCommand(['build', 'asgrim/example-pie-extension']);
    }

    /**
     * @Then /^the extension should have been built$/
     */
    public function theExtensionShouldHaveBeenBuilt()
    {
        $this->assertCommandSuccessful();
        Assert::contains($this->output, 'phpize complete.');
        Assert::contains($this->output, 'Configure complete.');
        Assert::contains($this->output, 'Build complete:');
    }
}
