<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\File;

use Composer\Util\Filesystem;
use Php\Pie\File\ReplaceRunningExecutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use Safe\Exceptions\FilesystemException;
use Symfony\Component\Process\Process;

use function microtime;
use function Safe\copy;
use function Safe\fclose;
use function Safe\file_get_contents;
use function Safe\fopen;
use function Safe\mkdir;
use function sys_get_temp_dir;
use function uniqid;
use function usleep;

use const DIRECTORY_SEPARATOR;

#[CoversClass(ReplaceRunningExecutable::class)]
#[RequiresOperatingSystemFamily('Windows')]
final class ReplaceRunningExecutableWindowsTest extends TestCase
{
    private string $path;
    private string $executable;
    private Process $runningExecutable;

    public function setUp(): void
    {
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_test_replace_running_executable_', true);
        mkdir($this->path);
        $this->executable = $this->path . DIRECTORY_SEPARATOR . 'pie.exe';
        copy('C:\\Windows\\System32\\PING.EXE', $this->executable);

        $this->runningExecutable = new Process([$this->executable, '-n', '30', '127.0.0.1']);
        $this->runningExecutable->start();
        $this->waitUntilRunning();
    }

    public function tearDown(): void
    {
        $this->runningExecutable->stop(0);
        (new Filesystem())->remove($this->path);
    }

    public function testRunningExecutableCanBeReplaced(): void
    {
        ReplaceRunningExecutable::contents($this->executable, 'the new executable');

        self::assertSame('the new executable', file_get_contents($this->executable));
    }

    private function waitUntilRunning(): void
    {
        $timeout = microtime(true) + 5;

        while (microtime(true) < $timeout) {
            try {
                fclose(fopen($this->executable, 'r+'));
            } catch (FilesystemException) {
                // Windows locks the file of a running executable against writes
                return;
            }

            usleep(10_000);
        }

        self::fail('Executable did not start in time');
    }
}
