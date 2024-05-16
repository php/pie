<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Command;

use Php\Pie\Command\DownloadCommand;
use Php\Pie\Container;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function array_combine;
use function array_map;
use function file_exists;
use function is_executable;

use const PHP_VERSION;
use const PHP_VERSION_ID;

#[CoversClass(DownloadCommand::class)]
class DownloadCommandTest extends TestCase
{
    private const TEST_PACKAGE = 'asgrim/example-pie-extension';

    private CommandTester $commandTester;

    public function setUp(): void
    {
        $this->commandTester = new CommandTester(Container::factory()->get(DownloadCommand::class));
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string, 1: non-empty-string}>
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public static function validVersionsList(): array
    {
        $versionsAndExpected = [
            [self::TEST_PACKAGE, self::TEST_PACKAGE . ':1.0.1'],
            [self::TEST_PACKAGE . ':^1.0', self::TEST_PACKAGE . ':1.0.1'],
            [self::TEST_PACKAGE . ':1.0.1-alpha.3@alpha', self::TEST_PACKAGE . ':1.0.1-alpha.3'],
            [self::TEST_PACKAGE . ':*', self::TEST_PACKAGE . ':1.0.1'],
            [self::TEST_PACKAGE . ':~1.0.0@alpha', self::TEST_PACKAGE . ':1.0.1'],
            [self::TEST_PACKAGE . ':^1.1.0@alpha', self::TEST_PACKAGE . ':1.1.0-alpha.1'],
            [self::TEST_PACKAGE . ':~1.0.0', self::TEST_PACKAGE . ':1.0.1'],
            // @todo in theory, these could work, on NonWindows at least
            //[self::TEST_PACKAGE . ':dev-master', self::TEST_PACKAGE . ':???'],
            //[self::TEST_PACKAGE . ':dev-master#769f906413d6d1e12152f6d34134cbcd347ca253', self::TEST_PACKAGE . ':???'],
        ];

        return array_combine(
            array_map(static fn ($item) => $item[0], $versionsAndExpected),
            $versionsAndExpected,
        );
    }

    #[DataProvider('validVersionsList')]
    public function testDownloadCommandWillDownloadCompatibleExtension(string $requestedVersion, string $expectedVersion): void
    {
        if (PHP_VERSION_ID < 80300 || PHP_VERSION_ID >= 80400) {
            self::markTestSkipped('This test can only run on PHP 8.3 - you are running ' . PHP_VERSION);
        }

        $this->commandTester->execute(['requested-package-and-version' => $requestedVersion]);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();
        self::assertStringContainsString('Found package: ' . $expectedVersion . ' which provides', $outputString);
        self::assertStringContainsString('Extracted ' . $expectedVersion . ' source to', $outputString);
    }

    #[DataProvider('validVersionsList')]
    public function testDownloadingWithPhpConfig(string $requestedVersion, string $expectedVersion): void
    {
        // @todo This test makes an assumption you're using `ppa:ondrej/php` to have multiple PHP versions. This allows
        //       us to test scenarios where you run with PHP 8.1 but want to install to a PHP 8.3 instance, for example.
        //       However, this test isn't very portable, and won't run in CI, so we could do with improving this later.
        $phpConfigPath = '/usr/bin/php-config8.3';

        if (! file_exists($phpConfigPath) || ! is_executable($phpConfigPath)) {
            self::markTestSkipped('This test can only run where "' . $phpConfigPath . '" exists and is executable, to target PHP 8.3');
        }

        $this->commandTester->execute([
            '--with-php-config' => $phpConfigPath,
            'requested-package-and-version' => $requestedVersion,
        ]);

        $this->commandTester->assertCommandIsSuccessful();

        $outputString = $this->commandTester->getDisplay();
        self::assertStringContainsString('Found package: ' . $expectedVersion . ' which provides', $outputString);
        self::assertStringContainsString('Extracted ' . $expectedVersion . ' source to', $outputString);
    }

    public function testDownloadCommandFailsWhenUsingIncompatiblePhpVersion(): void
    {
        if (PHP_VERSION_ID >= 80200) {
            self::markTestSkipped('This test can only run on older than PHP 8.2 - you are running ' . PHP_VERSION);
        }

        $this->expectException(UnableToResolveRequirement::class);
        // 1.0.0 is only compatible with PHP 8.3.0
        $this->commandTester->execute(['requested-package-and-version' => self::TEST_PACKAGE . ':1.0.0']);
    }
}
