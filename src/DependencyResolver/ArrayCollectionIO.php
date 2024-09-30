<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\IO\BaseIO;

use function array_merge;
use function is_array;

class ArrayCollectionIO extends BaseIO
{
    /** @var string[] */
    public array $errors = [];

    public function isInteractive(): bool
    {
        return false;
    }

    public function isVerbose(): bool
    {
        return true;
    }

    public function isVeryVerbose(): bool
    {
        return true;
    }

    public function isDebug(): bool
    {
        return true;
    }

    public function isDecorated(): bool
    {
        return false;
    }

    /** {@inheritDoc} */
    public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
    }

    /** {@inheritDoc} */
    public function writeError($messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        if (is_array($messages)) {
            $this->errors = array_merge($this->errors, $messages);

            return;
        }

        $this->errors[] = $messages;
    }

    /** {@inheritDoc} */
    public function overwrite($messages, bool $newline = true, int|null $size = null, int $verbosity = self::NORMAL): void
    {
    }

    /** {@inheritDoc} */
    public function overwriteError($messages, bool $newline = true, int|null $size = null, int $verbosity = self::NORMAL): void
    {
    }

    /** {@inheritDoc} */
    public function ask(string $question, $default = null): void
    {
    }

    public function askConfirmation(string $question, bool $default = true): void
    {
    }

    /** {@inheritDoc} */
    public function askAndValidate(string $question, callable $validator, int|null $attempts = null, $default = null): void
    {
    }

    public function askAndHideAnswer(string $question): void
    {
    }

    /** {@inheritDoc} */
    public function select(string $question, array $choices, $default, $attempts = false, string $errorMessage = 'Value "%s" is invalid', bool $multiselect = false): void
    {
    }
}
