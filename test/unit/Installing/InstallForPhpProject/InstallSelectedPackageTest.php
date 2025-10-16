<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\InstallForPhpProject;

use Composer\IO\BufferIO;
use Composer\Util\Platform;
use Php\Pie\File\FullPathToSelf;
use Php\Pie\Installing\InstallForPhpProject\InstallSelectedPackage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

use function getcwd;
use function trim;

#[CoversClass(InstallSelectedPackage::class)]
final class InstallSelectedPackageTest extends TestCase
{
    private const FAKE_HAPPY_SH  = __DIR__ . '/../../../assets/fake-pie-cli/happy.sh';
    private const FAKE_HAPPY_BAT = __DIR__ . '/../../../assets/fake-pie-cli/happy.bat';

    public function testWithPieCli(): void
    {
        $_SERVER['PHP_SELF'] = Platform::isWindows() ? self::FAKE_HAPPY_BAT : self::FAKE_HAPPY_SH;

        $input = new ArrayInput(
            ['--with-php-config' => '/path/to/php/config'],
            new InputDefinition([
                new InputOption('with-php-config', null, InputOption::VALUE_REQUIRED),
            ]),
        );
        $io    = new BufferIO();

        (new InstallSelectedPackage(new FullPathToSelf(getcwd())))->withPieCli(
            'foo/bar',
            $input,
            $io,
        );

        self::assertSame(
            '> Params passed: install foo/bar --with-php-config /path/to/php/config',
            trim($io->getOutput()),
        );
    }
}
