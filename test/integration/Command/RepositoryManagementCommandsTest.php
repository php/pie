<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Php\Pie\Command\RepositoryAddCommand;
use Php\Pie\Command\RepositoryListCommand;
use Php\Pie\Command\RepositoryRemoveCommand;
use Php\Pie\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function str_starts_with;
use function substr;

use const PHP_EOL;

#[CoversClass(RepositoryListCommand::class)]
#[CoversClass(RepositoryAddCommand::class)]
#[CoversClass(RepositoryRemoveCommand::class)]
final class RepositoryManagementCommandsTest extends TestCase
{
    private const EXAMPLE_PATH_REPOSITORY_URL = __DIR__;
    private const EXAMPLE_VCS_REPOSITORY_URL  = 'https://github.com/asgrim/example-pie-extension';

    private CommandTester $listCommand;
    private CommandTester $addCommand;
    private CommandTester $removeCommand;

    public function setUp(): void
    {
        $this->listCommand   = new CommandTester(Container::factory()->get(RepositoryListCommand::class));
        $this->addCommand    = new CommandTester(Container::factory()->get(RepositoryAddCommand::class));
        $this->removeCommand = new CommandTester(Container::factory()->get(RepositoryRemoveCommand::class));

        $this->removeCommand->execute(['url' => self::EXAMPLE_PATH_REPOSITORY_URL]);
        $this->removeCommand->execute(['url' => self::EXAMPLE_VCS_REPOSITORY_URL]);
        $this->removeCommand->execute(['url' => self::EXAMPLE_VCS_REPOSITORY_URL . '.git']);
    }

    public function testPathRepositoriesCanBeManaged(): void
    {
        $this->assertRepositoryListDisplayed(['Packagist']);

        $this->addCommand->execute([
            'type' => 'path',
            'url' => self::EXAMPLE_PATH_REPOSITORY_URL,
        ]);

        $this->assertRepositoryListDisplayed(
            [
                'Path Repository (' . self::EXAMPLE_PATH_REPOSITORY_URL . ')',
                'Packagist',
            ],
        );

        $this->removeCommand->execute(['url' => self::EXAMPLE_PATH_REPOSITORY_URL]);
        $this->assertRepositoryListDisplayed(['Packagist']);
    }

    public function testVcsRepositoriesCanBeManaged(): void
    {
        $this->assertRepositoryListDisplayed(['Packagist']);

        $this->addCommand->execute([
            'type' => 'vcs',
            'url' => self::EXAMPLE_VCS_REPOSITORY_URL,
        ]);

        $this->assertRepositoryListDisplayed(
            [
                'VCS Repository (' . self::EXAMPLE_VCS_REPOSITORY_URL . '.git)',
                'Packagist',
            ],
        );

        $this->removeCommand->execute(['url' => self::EXAMPLE_VCS_REPOSITORY_URL]);
        $this->assertRepositoryListDisplayed(['Packagist']);
    }

    /** @param list<non-empty-string> $expectedRepositories */
    private function assertRepositoryListDisplayed(array $expectedRepositories): void
    {
        $this->listCommand->execute([]);
        $this->listCommand->assertCommandIsSuccessful();

        $outputString = $this->listCommand->getDisplay();

        self::assertEquals(
            $expectedRepositories,
            array_values(array_map(
                static fn ($line) => substr($line, 4),
                array_filter(
                    explode(PHP_EOL, $outputString),
                    static fn ($line): bool => str_starts_with($line, '  - '),
                ),
            )),
        );
    }
}
