<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\InstallForPhpProject;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\RepositoryManager;
use Composer\Semver\Constraint\Constraint;
use Php\Pie\Installing\InstallForPhpProject\DetermineExtensionsRequired;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DetermineExtensionsRequired::class)]
final class DetermineExtensionsRequiredTest extends TestCase
{
    private function composerFor(RootPackage $rootPackage, InstalledArrayRepository $installedRepository): Composer
    {
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $repositoryManager->method('getLocalRepository')->willReturn($installedRepository);

        $composer = $this->createMock(Composer::class);
        $composer->method('getPackage')->willReturn($rootPackage);
        $composer->method('getRepositoryManager')->willReturn($repositoryManager);

        return $composer;
    }

    public function testForProjectIncludesDevRequiresByDefault(): void
    {
        $rootPackage = new RootPackage('my/project', '1.2.3.0', '1.2.3');
        $rootPackage->setRequires(['ext-redis' => new Link('my/project', 'ext-redis', new Constraint('=', '*'), Link::TYPE_REQUIRE, '*')]);
        $rootPackage->setDevRequires(['ext-xdebug' => new Link('my/project', 'ext-xdebug', new Constraint('=', '*'), Link::TYPE_DEV_REQUIRE, '*')]);

        $composer = $this->composerFor($rootPackage, new InstalledArrayRepository([$rootPackage]));

        $requires = (new DetermineExtensionsRequired())->forProject($composer);

        self::assertArrayHasKey('ext-redis', $requires);
        self::assertArrayHasKey('ext-xdebug', $requires);
    }

    public function testForProjectExcludesRootDevRequiresWhenNoDevIsTrue(): void
    {
        $rootPackage = new RootPackage('my/project', '1.2.3.0', '1.2.3');
        $rootPackage->setRequires(['ext-redis' => new Link('my/project', 'ext-redis', new Constraint('=', '*'), Link::TYPE_REQUIRE, '*')]);
        $rootPackage->setDevRequires(['ext-xdebug' => new Link('my/project', 'ext-xdebug', new Constraint('=', '*'), Link::TYPE_DEV_REQUIRE, '*')]);

        $composer = $this->composerFor($rootPackage, new InstalledArrayRepository([$rootPackage]));

        $requires = (new DetermineExtensionsRequired())->forProject($composer, true);

        self::assertArrayHasKey('ext-redis', $requires);
        self::assertArrayNotHasKey('ext-xdebug', $requires);
    }

    public function testForProjectStillIncludesRequiresFromNonDevInstalledPackagesWhenNoDevIsTrue(): void
    {
        $rootPackage = new RootPackage('my/project', '1.2.3.0', '1.2.3');
        $rootPackage->setDevRequires(['ext-xdebug' => new Link('my/project', 'ext-xdebug', new Constraint('=', '*'), Link::TYPE_DEV_REQUIRE, '*')]);

        $dependencyPackage = new CompletePackage('vendor/some-lib', '1.0.0.0', '1.0.0');
        $dependencyPackage->setRequires(['ext-mbstring' => new Link('vendor/some-lib', 'ext-mbstring', new Constraint('=', '*'), Link::TYPE_REQUIRE, '*')]);

        $installedRepository = new InstalledArrayRepository([$rootPackage, $dependencyPackage]);
        $installedRepository->setDevPackageNames([]);

        $composer = $this->composerFor($rootPackage, $installedRepository);

        $requires = (new DetermineExtensionsRequired())->forProject($composer, true);

        self::assertArrayHasKey('ext-mbstring', $requires);
        self::assertArrayNotHasKey('ext-xdebug', $requires);
    }
}
