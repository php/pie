<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\InstallForPhpProject;

use Composer\IO\BufferIO;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\Version\VersionParser;
use Composer\Semver\Constraint\MatchAllConstraint;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\InstallForPhpProject\CheckExtensionStatus;
use Php\Pie\Platform\PiePackageList;
use Php\Pie\Util\Emoji;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckExtensionStatus::class)]
final class CheckExtensionStatusTest extends TestCase
{
    private BufferIO $io;
    private CheckExtensionStatus $checkExtensionStatus;

    public function setUp(): void
    {
        parent::setUp();

        $this->io                   = new BufferIO();
        $this->checkExtensionStatus = new CheckExtensionStatus($this->io);
    }

    private function makePackage(string $version): Package
    {
        $composerPackage = new CompletePackage('vendor/foobar', $version . '.0', $version);
        $composerPackage->setType(ExtensionType::PhpModule->value);

        return new Package(
            $composerPackage,
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foobar'),
            'vendor/foobar',
            $version,
            null,
        );
    }

    public function testAlreadyInstalledWithNoVersionInfo(): void
    {
        $link = new Link('my/project', 'ext-foobar', new MatchAllConstraint(), Link::TYPE_REQUIRE, '*');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([]),
            ['foobar'],
        );

        self::assertTrue($result);
        self::assertStringContainsString('Already installed', $this->io->getOutput());
        self::assertStringContainsString(Emoji::GREEN_CHECKMARK, $this->io->getOutput());
    }

    public function testAlreadyInstalledWithMatchingVersion(): void
    {
        $link = new Link('my/project', 'ext-foobar', (new VersionParser())->parseConstraints('^1.0'), Link::TYPE_REQUIRE, '^1.0');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([$this->makePackage('1.2.0')]),
            ['foobar'],
        );

        self::assertTrue($result);
        self::assertStringContainsString('Already installed', $this->io->getOutput());
        self::assertStringContainsString(Emoji::GREEN_CHECKMARK, $this->io->getOutput());
    }

    public function testVersionMismatchWarning(): void
    {
        $link = new Link('my/project', 'ext-foobar', (new VersionParser())->parseConstraints('^2.0'), Link::TYPE_REQUIRE, '^2.0');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([$this->makePackage('1.2.0')]),
            ['foobar'],
        );

        self::assertTrue($result);
        self::assertStringContainsString('Version 1.2.0 is installed, but does not meet the version requirement', $this->io->getOutput());
        self::assertStringContainsString(Emoji::WARNING, $this->io->getOutput());
    }

    public function testMissingExtension(): void
    {
        $link = new Link('my/project', 'ext-foobar', new MatchAllConstraint(), Link::TYPE_REQUIRE, '*');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([]),
            [],
        );

        self::assertFalse($result);
        self::assertStringContainsString('Missing', $this->io->getOutput());
        self::assertStringContainsString(Emoji::PROHIBITED, $this->io->getOutput());
    }
}
