<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Command;

use Php\Pie\Command\ArgvInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Throwable;

#[CoversClass(ArgvInput::class)]
final class ArgvInputTest extends TestCase
{
    /** @return array<string, list<list<string>>> */
    public static function argvWithInvalidInputProvider(): array
    {
        return [
            'simple-option' => [['myapp', '--invalid-option', 'myvalue']],
            'value-option' => [['myapp', '--invalid-option=foo', 'myvalue']],
            'short-option' => [['myapp', '-i', 'myvalue']],
            // explicitly not supported for now; we can't tell which is the argument here
            // 'split-option' => [['myapp', '--invalid-option', 'foo', 'myvalue']],
        ];
    }

    /** @param list<string> $argv */
    #[DataProvider('argvWithInvalidInputProvider')]
    public function testInvalidOptionsDoNotCauseArgumentsToBeMissed(array $argv): void
    {
        $definition = new InputDefinition();
        $definition->addArgument(new InputArgument('myarg', InputArgument::OPTIONAL));

        $argvInput = new ArgvInput($argv);
        try {
            $argvInput->bind($definition);
            self::fail('Expected an exception to be thrown because `--invalid-option` is not defined');
        } catch (Throwable) {
            // An exception is expected here, because `--invalid-option` was not defined
            self::addToAssertionCount(1);
        }

        // But, crucially, we should have captured the following argument
        self::assertSame('myvalue', $argvInput->getArgument('myarg'));
    }
}
