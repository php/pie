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
use Php\Pie\Installing\InstallForPhpProject\ExtensionInstalledStatus;
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

    public function testPiePackageAlreadyInstalledWithMatchingVersion(): void
    {
        $link = new Link('my/project', 'ext-foobar', (new VersionParser())->parseConstraints('^1.0'), Link::TYPE_REQUIRE, '^1.0');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([$this->makePackage('1.2.0')]),
            ['foobar' => '0'],
        );

        self::assertSame(ExtensionInstalledStatus::Installed, $result);
        self::assertStringContainsString('Already installed', $this->io->getOutput());
        self::assertStringContainsString(Emoji::GREEN_CHECKMARK, $this->io->getOutput());
    }

    public function testPiePackageVersionMismatchWarning(): void
    {
        $link = new Link('my/project', 'ext-foobar', (new VersionParser())->parseConstraints('^2.0'), Link::TYPE_REQUIRE, '^2.0');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([$this->makePackage('1.2.0')]),
            ['foobar' => '0'],
        );

        self::assertSame(ExtensionInstalledStatus::ConstraintNotMet, $result);
        self::assertStringContainsString('Version 1.2.0 is installed, but does not meet the version requirement', $this->io->getOutput());
        self::assertStringContainsString(Emoji::WARNING, $this->io->getOutput());
    }

    public function testNonPieExtensionPresentAndMatchesConstraint(): void
    {
        $link = new Link('my/project', 'ext-foobar', (new VersionParser())->parseConstraints('^2.0'), Link::TYPE_REQUIRE, '^2.0');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([]),
            ['foobar' => '2.0.5'],
        );

        self::assertSame(ExtensionInstalledStatus::Installed, $result);
        self::assertStringContainsString('Already installed', $this->io->getOutput());
        self::assertStringContainsString(Emoji::GREEN_CHECKMARK, $this->io->getOutput());
    }

    public function testNonPieExtensionPresentButVersionMismatch(): void
    {
        $link = new Link('my/project', 'ext-foobar', (new VersionParser())->parseConstraints('^2.0'), Link::TYPE_REQUIRE, '^2.0');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([]),
            ['foobar' => '1.2.0'],
        );

        self::assertSame(ExtensionInstalledStatus::ConstraintNotMet, $result);
        self::assertStringContainsString('Version 1.2.0 is installed, but does not meet the version requirement', $this->io->getOutput());
        self::assertStringContainsString(Emoji::WARNING, $this->io->getOutput());
    }

    public function testNonPieExtensionPresentWithUnparseableVersion(): void
    {
        $link = new Link('my/project', 'ext-foobar', (new VersionParser())->parseConstraints('^2.0'), Link::TYPE_REQUIRE, '^2.0');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([]),
            ['foobar' => '1.2.3-abcdef'],
        );

        self::assertSame(ExtensionInstalledStatus::ConstraintNotMet, $result);
        self::assertStringContainsString('Version 1.2.3-abcdef is installed, but does not meet the version requirement', $this->io->getOutput());
        self::assertStringContainsString(Emoji::WARNING, $this->io->getOutput());
    }

    public function testNonPieExtensionPresentWithNoVersionInfo(): void
    {
        $link = new Link('my/project', 'ext-foobar', (new VersionParser())->parseConstraints('^2.0'), Link::TYPE_REQUIRE, '^2.0');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([]),
            ['foobar' => '0'],
        );

        self::assertSame(ExtensionInstalledStatus::ConstraintNotMet, $result);
        self::assertStringContainsString('Version 0 is installed, but does not meet the version requirement', $this->io->getOutput());
        self::assertStringContainsString(Emoji::WARNING, $this->io->getOutput());
    }

    public function testNonPieExtensionPresentWithNoVersionInfoButMatchesAll(): void
    {
        $link = new Link('my/project', 'ext-foobar', new MatchAllConstraint(), Link::TYPE_REQUIRE, '*');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([]),
            ['foobar' => '0'],
        );

        self::assertSame(ExtensionInstalledStatus::Installed, $result);
        self::assertStringContainsString('Already installed', $this->io->getOutput());
        self::assertStringContainsString(Emoji::GREEN_CHECKMARK, $this->io->getOutput());
    }

    public function testMissingExtension(): void
    {
        $link = new Link('my/project', 'ext-foobar', new MatchAllConstraint(), Link::TYPE_REQUIRE, '*');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([]),
            [],
        );

        self::assertSame(ExtensionInstalledStatus::Missing, $result);
        self::assertStringContainsString('Missing', $this->io->getOutput());
        self::assertStringContainsString(Emoji::PROHIBITED, $this->io->getOutput());
    }

    public function testBundledExtensionPresentAndUnconstrained(): void
    {
        $link = new Link('my/project', 'ext-json', new MatchAllConstraint(), Link::TYPE_REQUIRE, '*');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([]),
            ['json' => '8.5.11'],
        );

        self::assertSame(ExtensionInstalledStatus::Installed, $result);
    }

    public function testBundledExtensionPresentAndMatchesConstraints(): void
    {
        $link = new Link('my/project', 'ext-json', (new VersionParser())->parseConstraints('8.5.*'), Link::TYPE_REQUIRE, '8.5.*');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([]),
            ['json' => '8.5.11'],
        );

        self::assertSame(ExtensionInstalledStatus::Installed, $result);
    }

    public function testBundledExtensionPresentButDoesNotMatchConstraints(): void
    {
        $link = new Link('my/project', 'ext-json', (new VersionParser())->parseConstraints('8.5.*'), Link::TYPE_REQUIRE, '8.5.*');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([]),
            ['json' => '8.4.26'],
        );

        self::assertSame(ExtensionInstalledStatus::ConstraintNotMet, $result);
        self::assertStringContainsString('Version 8.4.26 is installed, but does not meet the version requirement', $this->io->getOutput());
    }

    public function testBundledExtensionNotPresentButIsRequired(): void
    {
        $link = new Link('my/project', 'ext-json', new MatchAllConstraint(), Link::TYPE_REQUIRE, '*');

        $result = ($this->checkExtensionStatus)(
            $link,
            new PiePackageList([]),
            [],
        );

        self::assertSame(ExtensionInstalledStatus::Missing, $result);
    }
}
