<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Throwable;

class ArgvInput extends \Symfony\Component\Console\Input\ArgvInput
{
    private Throwable|null $exceptionThrown = null;

    /**
     * Wrap parent token parsing to collect and ignore exceptions during
     * parsing. This ensures that errors we meet mid-way through parsing don't
     * short-circuit processing the rest of the arguments. Without this, whilst
     * the following example works:
     *
     *     pie build asgrim/example-pie-extension --with-hello-name=sup
     *
     * This does not:
     *
     *     pie build --with-hello-name=sup asgrim/example-pie-extension
     *
     * This is because when Symfony tries to parse the `--with-hello-name`, it
     * hasn't loaded in the configure options for the package yet, and so
     * throws an exception and does not process the package name argument.
     *
     * Note, however, there is still a limitation, as this will not work:
     *
     *      pie build --with-hello-name sup asgrim/example-pie-extension
     */
    protected function parse(): void
    {
        $this->exceptionThrown = null;

        parent::parse();

        if ($this->exceptionThrown !== null) {
            throw $this->exceptionThrown;
        }
    }

    protected function parseToken(string $token, bool $parseOptions): bool
    {
        try {
            return parent::parseToken($token, $parseOptions);
        } catch (Throwable $caught) {
            $this->exceptionThrown = $caught;

            // Ignore the error intentionally
            return $parseOptions;
        }
    }
}
