<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Composer\IO\BufferIO;
use Php\Pie\Container;
use ReflectionProperty;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester as SymfonyCommandTester;

use function assert;
use function ftruncate;

class CommandTester extends SymfonyCommandTester
{
    /**
     * @param array<array-key, mixed> $input
     * @param array<array-key, mixed> $options
     *
     * @inheritDoc
     */
    public function execute(array $input, array $options = []): int
    {
        $buffer = Container::testBuffer();
        $output = (new ReflectionProperty(BufferIO::class, 'output'))->getValue($buffer);
        assert($output instanceof StreamOutput);
        $stream = $output->getStream();
        ftruncate($stream, 0);

        return parent::execute($input, $options);
    }

    public function getDisplay(bool $normalize = false): string
    {
        return Container::testBuffer()->getOutput();
    }
}
