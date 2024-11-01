<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Composer\Package\PackageInterface;
use Php\Pie\ComposerIntegration\QuieterConsoleIO;
use Php\Pie\DependencyResolver\RequestedPackageAndVersion;
use Php\Pie\DependencyResolver\UnableToResolveRequirement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(UnableToResolveRequirement::class)]
final class UnableToResolveRequirementTest extends TestCase
{
    public function testToPhpOrZendExtensionWithVersion(): void
    {
        $package = $this->createMock(PackageInterface::class);
        $package->method('getName')->willReturn('baz/bat');

        $exception = UnableToResolveRequirement::toPhpOrZendExtension($package, new RequestedPackageAndVersion('foo/bar', '^1.2'));

        self::assertSame('Package baz/bat was not of type php-ext or php-ext-zend (requested foo/bar for version ^1.2).', $exception->getMessage());
    }

    public function testToPhpOrZendExtensionWithoutVersion(): void
    {
        $package = $this->createMock(PackageInterface::class);
        $package->method('getName')->willReturn('baz/bat');

        $exception = UnableToResolveRequirement::toPhpOrZendExtension($package, new RequestedPackageAndVersion('foo/bar', null));

        self::assertSame('Package baz/bat was not of type php-ext or php-ext-zend (requested foo/bar).', $exception->getMessage());
    }

    public function testFromRequirementWithVersion(): void
    {
        $io = new QuieterConsoleIO(
            $this->createMock(InputInterface::class),
            $this->createMock(OutputInterface::class),
            $this->createMock(HelperSet::class),
        );
        $io->writeError('message1');
        $io->writeError('message2', true, QuieterConsoleIO::VERY_VERBOSE);
        $io->writeError(['message3', 'message4']);

        $exception = UnableToResolveRequirement::fromRequirement(new RequestedPackageAndVersion('foo/bar', '^1.2'), $io);

        self::assertSame("Unable to find an installable package foo/bar for version ^1.2.\n\nmessage1\n\nmessage3\n\nmessage4", $exception->getMessage());
    }

    public function testFromRequirementWithoutVersion(): void
    {
        $io = new QuieterConsoleIO(
            $this->createMock(InputInterface::class),
            $this->createMock(OutputInterface::class),
            $this->createMock(HelperSet::class),
        );
        $io->writeError('message1');
        $io->writeError('message2', true, QuieterConsoleIO::VERY_VERBOSE);
        $io->writeError(['message3', 'message4']);

        $exception = UnableToResolveRequirement::fromRequirement(new RequestedPackageAndVersion('foo/bar', null), $io);

        self::assertSame("Unable to find an installable package foo/bar.\n\nmessage1\n\nmessage3\n\nmessage4", $exception->getMessage());
    }
}
