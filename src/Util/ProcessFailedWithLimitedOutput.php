<?php

declare(strict_types=1);

namespace Php\Pie\Util;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use function file_put_contents;
use function sprintf;
use function strlen;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

class ProcessFailedWithLimitedOutput extends ProcessFailedException
{
    public static function fromProcessFailedException(ProcessFailedException $previous): self|ProcessFailedException
    {
        $process = $previous->getProcess();

        if (strlen($process->getOutput()) > 5000) {
            $tempOutputFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pie_make_output_', true);
            file_put_contents(
                $tempOutputFile,
                sprintf(
                    "Output:\n================\n%s\n\nError Output:\n================\n%s",
                    $process->getOutput(),
                    $process->getErrorOutput(),
                ),
            );

            return new self($process, $tempOutputFile);
        }

        return $previous;
    }

    public function __construct(Process $process, string $fileWithOutput)
    {
        parent::__construct($process);

        $error = sprintf(
            'The command "%s" failed. Output was saved in "%s" as it was too long' . "\n\nExit Code: %s(%s)\n\nWorking directory: %s",
            $process->getCommandLine(),
            $fileWithOutput,
            $process->getExitCode() ?? '?',
            $process->getExitCodeText() ?? '?',
            $process->getWorkingDirectory() ?? '?',
        );

        $this->message = $error;
    }
}
