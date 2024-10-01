<?php

declare(strict_types=1);

namespace Php\PieBehaviourTest;

use Behat\Behat\Context\Context;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

class CliContext implements Context
{
    private string|null $output;

    private string|null $errorOutput;

    private int|null $exitCode;

    /**
     * @When I run PIE command :command
     */
    public function iRunCommand(string $command) : void
    {
        $pieCommand = array_merge(['php', 'bin/pie'], explode(' ', $command));

        $proc = (new Process($pieCommand))->mustRun();

        $this->output = $proc->getOutput();
        $this->errorOutput = $proc->getErrorOutput();
        $this->exitCode = $proc->getExitCode();
    }

    /**
     * @Then the latest version should have been downloaded
     */
    public function theLatestVersionShouldHaveBeenDownloaded() : void
    {
        Assert::same(0, $this->exitCode);

        Assert::regex($this->output, '#Found package: apcu/apcu:v?\d+\.\d+\.\d+ which provides ext-apcu#');
        Assert::regex($this->output, '#Extracted apcu/apcu:v?\d+\.\d+\.\d+ source to: .+/krakjoe-apcu-[a-z0-9]+#');
    }

    /**
     * @Then version :version of the extension should have been downloaded
     */
    public function versionOfTheExtensionShouldHaveBeen(string $version)
    {
        Assert::same(0, $this->exitCode);

        Assert::contains($this->output, 'Found package: xdebug/xdebug:' . $version . ' which provides ext-xdebug');
    }
}
