<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Composer\Package\CompletePackage;
use InvalidArgumentException;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\OperatingSystemFamily;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Package::class)]
final class PackageTest extends TestCase
{
    public function testFromComposerCompletePackage(): void
    {
        $package = Package::fromComposerCompletePackage(
            new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3'),
        );

        self::assertSame('foo', $package->extensionName->name());
        self::assertSame('vendor/foo', $package->name);
        self::assertSame('1.2.3', $package->version);
        self::assertSame('vendor/foo:1.2.3', $package->prettyNameAndVersion());
        self::assertNull($package->downloadUrl);
        self::assertNull($package->buildPath);
    }

    public function testFromComposerCompletePackageWithExtensionName(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['extension-name' => 'ext-something_else']);

        $package = Package::fromComposerCompletePackage($composerCompletePackage);

        self::assertSame('something_else', $package->extensionName->name());
        self::assertSame('vendor/foo', $package->name);
        self::assertSame('1.2.3', $package->version);
        self::assertSame('vendor/foo:1.2.3', $package->prettyNameAndVersion());
        self::assertNull($package->downloadUrl);
    }

    public function testFromComposerCompletePackageWithExcludedOsFamilies(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['os-families-exclude' => ['windOWS', 'DarWIN']]);

        $package = Package::fromComposerCompletePackage($composerCompletePackage);

        self::assertSame([OperatingSystemFamily::Windows, OperatingSystemFamily::Darwin], $package->incompatibleOsFamilies);
        self::assertSame('vendor/foo', $package->name);
        self::assertSame('1.2.3', $package->version);
        self::assertSame('vendor/foo:1.2.3', $package->prettyNameAndVersion());
        self::assertNull($package->downloadUrl);
    }

    public function testFromComposerCompletePackageWithOsFamilies(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['os-families' => ['windOWS', 'DarWiN']]);

        $package = Package::fromComposerCompletePackage($composerCompletePackage);

        self::assertEmpty($package->incompatibleOsFamilies);
        self::assertSame([OperatingSystemFamily::Windows, OperatingSystemFamily::Darwin], $package->compatibleOsFamilies);
        self::assertSame('vendor/foo', $package->name);
        self::assertSame('1.2.3', $package->version);
        self::assertSame('vendor/foo:1.2.3', $package->prettyNameAndVersion());
        self::assertNull($package->downloadUrl);
    }

    public function testFromComposerCompletePackageWithEmptyExcludedOsFamilies(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['os-families-exclude' => null]);

        $package = Package::fromComposerCompletePackage($composerCompletePackage);

        self::assertEmpty($package->compatibleOsFamilies);
        self::assertEmpty($package->incompatibleOsFamilies);
        self::assertSame('vendor/foo', $package->name);
        self::assertSame('1.2.3', $package->version);
        self::assertSame('vendor/foo:1.2.3', $package->prettyNameAndVersion());
        self::assertNull($package->downloadUrl);
    }

    public function testFromComposerCompletePackageWithEmptyOsFamilies(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['os-families' => null]);

        $package = Package::fromComposerCompletePackage($composerCompletePackage);

        self::assertEmpty($package->compatibleOsFamilies);
        self::assertEmpty($package->incompatibleOsFamilies);
        self::assertSame('vendor/foo', $package->name);
        self::assertSame('1.2.3', $package->version);
        self::assertSame('vendor/foo:1.2.3', $package->prettyNameAndVersion());
        self::assertNull($package->downloadUrl);
    }

    public function testFromComposerCompletePackageWithBothOsFamiliesAndExcludedOsFamiliesThrows(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['os-families-exclude' => ['Windows'], 'os-families' => ['Darwin']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot specify both "os-families" and "os-families-exclude" in composer.json');

        Package::fromComposerCompletePackage($composerCompletePackage);
    }

    public function testFromComposerCompletePackageWithTypeInOsFamiliesThrows(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['os-families' => ['Not an OS']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected operating system family to be one of "Windows", "BSD", "Darwin", "Solaris", "Linux", "Unknown", got "Not an OS".');

        Package::fromComposerCompletePackage($composerCompletePackage);
    }

    public function testFromComposerCompletePackageWithTypeInExcludedOsFamiliesThrows(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['os-families-exclude' => ['Not an OS']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected operating system family to be one of "Windows", "BSD", "Darwin", "Solaris", "Linux", "Unknown", got "Not an OS".');

        Package::fromComposerCompletePackage($composerCompletePackage);
    }

    /**
     * @return array<string, array{0: string, 1: string|null, 2: string}>
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public static function githubOrgAndRepoFromPackage(): array
    {
        return [
            'noDownloadUrl' => ['foo/bar', null, 'foo/bar'],
            'gitlabMatchingPackage' => ['foo/bar', 'https://gitlab.com/api/v4/projects/foo%2Fbar/repository/archive.zip?sha=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'foo/bar'],
            'gitlabDifferentPackage' => ['foo/bar', 'https://gitlab.com/api/v4/projects/abc%2Fdef/repository/archive.zip?sha=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'foo/bar'],
            'githubMatchingPackage' => ['foo/bar', 'https://api.github.com/repos/foo/bar/zipball/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'foo/bar'],
            'githubDifferentPackage' => ['foo/bar', 'https://api.github.com/repos/abc/def/zipball/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'abc/def'],
            'githubIncompleteUrl' => ['foo/bar', 'https://api.github.com/', 'foo/bar'],
        ];
    }

    #[DataProvider('githubOrgAndRepoFromPackage')]
    public function testGithubOrgAndRepo(string $composerPackageName, string|null $downloadUrl, string $expectedGithubOrgAndRepo): void
    {
        $package = new Package(
            $this->createMock(CompletePackage::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            $composerPackageName,
            '1.2.3',
            $downloadUrl,
            [],
            true,
            true,
            null,
            null,
            null,
        );

        self::assertSame($expectedGithubOrgAndRepo, $package->githubOrgAndRepository());
    }

    public function testFromComposerCompletePackageWithBuildPath(): void
    {
        $composerCompletePackage = new CompletePackage('vendor/foo', '1.2.3.0', '1.2.3');
        $composerCompletePackage->setPhpExt(['build-path' => 'some/subdirectory/path/']);

        $package = Package::fromComposerCompletePackage($composerCompletePackage);

        self::assertSame('vendor/foo:1.2.3', $package->prettyNameAndVersion());
        self::assertSame('some/subdirectory/path/', $package->buildPath);
    }
}
