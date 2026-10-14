<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\File;

use Composer\Util\Filesystem;
use Composer\Util\Platform as ComposerPlatform;
use Php\Pie\File\ReplaceRunningExecutable;
use Php\Pie\File\Sudo;
use Php\Pie\File\SudoNotFoundOnSystem;
use Php\Pie\File\SudoRequiresInteractiveTerminal;
use Php\Pie\Platform\TargetPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresOperatingSystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

use function file_exists;
use function filegroup;
use function is_executable;
use function is_string;
use function microtime;
use function Safe\chmod;
use function Safe\copy;
use function Safe\file_get_contents;
use function Safe\fileowner;
use function Safe\mkdir;
use function Safe\readlink;
use function sys_get_temp_dir;
use function uniqid;
use function usleep;

use const DIRECTORY_SEPARATOR;

#[CoversClass(ReplaceRunningExecutable::class)]
#[RequiresOperatingSystem('Linux')]
final class ReplaceRunningExecutableNonWindowsTest extends TestCase
{
    private string $path;
    private string $executable;
    private Process $runningExecutable;
    private string|false|null $originalComposerNoInteraction = null;

    public function setUp(): void
    {
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_replace_running_executable_', true);
        mkdir($this->path);
        $this->executable = $this->path . DIRECTORY_SEPARATOR . 'pie';
        copy('/bin/sleep', $this->executable);
        chmod($this->executable, 0755);

        $this->runningExecutable = new Process([$this->executable, '30']);
        $this->runningExecutable->start();
        $this->waitUntilRunning();
    }

    public function tearDown(): void
    {
        $this->runningExecutable->stop(0);
        chmod($this->path, 0755);

        if (is_string($this->originalComposerNoInteraction)) {
            ComposerPlatform::putEnv('COMPOSER_NO_INTERACTION', $this->originalComposerNoInteraction);
        }

        (new Filesystem())->remove($this->path);
    }

    public function testRunningExecutableCanBeReplaced(): void
    {
        ReplaceRunningExecutable::contents($this->executable, 'the new executable');

        self::assertSame('the new executable', file_get_contents($this->executable));
        self::assertTrue(is_executable($this->executable));
    }

    public function testRunningExecutableInUnwritableDirectoryCanBeReplaced(): void
    {
        $this->skipUnlessSudoIsNeededAndAvailable();
        chmod($this->path, 0555);

        ReplaceRunningExecutable::contents($this->executable, 'the new executable');

        self::assertSame('the new executable', file_get_contents($this->executable));
        self::assertTrue(is_executable($this->executable));
    }

    public function testRootOwnedRunningExecutableInUnwritableDirectoryCanBeReplaced(): void
    {
        $this->skipUnlessSudoIsNeededAndAvailable();
        chmod($this->executable, 0555);
        (new Process([Sudo::find(), 'chown', '0:0', $this->executable]))->mustRun();
        chmod($this->path, 0555);

        ReplaceRunningExecutable::contents($this->executable, 'the new executable');

        self::assertSame('the new executable', file_get_contents($this->executable));
        self::assertTrue(is_executable($this->executable));
        self::assertSame(0, fileowner($this->executable));
        self::assertSame(0, filegroup($this->executable));
    }

    private function skipUnlessSudoIsNeededAndAvailable(): void
    {
        if (TargetPlatform::isRunningAsRoot()) {
            self::markTestSkipped('Running as root, so file permissions do not apply');
        }

        $this->originalComposerNoInteraction = ComposerPlatform::getEnv('COMPOSER_NO_INTERACTION');
        ComposerPlatform::clearEnv('COMPOSER_NO_INTERACTION');

        try {
            Sudo::find();
        } catch (SudoNotFoundOnSystem | SudoRequiresInteractiveTerminal) {
            self::markTestSkipped('Cannot test without sudo in an interactive terminal');
        }
    }

    private function waitUntilRunning(): void
    {
        $procExe = '/proc/' . $this->runningExecutable->getPid() . '/exe';
        $timeout = microtime(true) + 5;

        while (microtime(true) < $timeout) {
            if (file_exists($procExe) && readlink($procExe) === $this->executable) {
                return;
            }

            usleep(10_000);
        }

        self::fail('Executable did not start in time');
    }
}
