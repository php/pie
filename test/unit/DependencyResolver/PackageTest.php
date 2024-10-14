<?php

declare(strict_types=1);

namespace Php\PieUnitTest\DependencyResolver;

use Composer\Package\CompletePackage;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
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

    /**
     * @return array<string, array{0: string, 1: string|null, 2: string}>
     *
     * @psalm-suppress PossiblyUnusedMethod https://github.com/psalm/psalm-plugin-phpunit/issues/131
     */
    public function githubOrgAndRepoFromPackage(): array
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
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('foo'),
            $composerPackageName,
            '1.2.3',
            $downloadUrl,
            [],
            null,
            '1.2.3.0',
            true,
            true,
        );

        self::assertSame($expectedGithubOrgAndRepo, $package->githubOrgAndRepository());
    }
}
