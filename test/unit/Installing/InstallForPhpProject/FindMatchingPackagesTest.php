<?php

declare(strict_types=1);

namespace Php\PieUnitTest\Installing\InstallForPhpProject;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Repository\ArrayRepository;
use Composer\Repository\RepositoryManager;
use Composer\Util\HttpDownloader;
use Php\Pie\ExtensionType;
use Php\Pie\Installing\InstallForPhpProject\FindMatchingPackages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FindMatchingPackages::class)]
final class FindMatchingPackagesTest extends TestCase
{
    public function testSearchResultsAreFilteredByExtensionName(): void
    {
        $repository = new ArrayRepository([
            (static function (): CompletePackage {
                $package = new CompletePackage('another/bar', '1.5.0.0', '1.5.0');
                $package->setDescription('These are not the extensions you are looking for');
                $package->setType(ExtensionType::PhpModule->value);
                $package->setPhpExt(['extension-name' => 'something_else']);

                return $package;
            })(),
            (static function (): CompletePackage {
                $package = new CompletePackage('foo/bar', '1.2.3.0', '1.2.3');
                $package->setDescription('The best extension there is');
                $package->setType(ExtensionType::PhpModule->value);

                return $package;
            })(),
            (static function (): CompletePackage {
                $package = new CompletePackage('foo/bar', '2.0.0.0', '2.0.0');
                $package->setDescription('The best extension there is');
                $package->setType(ExtensionType::PhpModule->value);

                return $package;
            })(),
        ]);

        $repoManager = new RepositoryManager(
            $this->createMock(IOInterface::class),
            $this->createMock(Config::class),
            $this->createMock(HttpDownloader::class),
            null,
            null,
        );
        $repoManager->addRepository($repository);

        $composer = $this->createMock(Composer::class);
        $composer->method('getRepositoryManager')->willReturn($repoManager);

        self::assertSame(
            [
                [
                    'name' => 'foo/bar',
                    'description' => 'The best extension there is',
                ],
            ],
            (new FindMatchingPackages())->for($composer, 'bar'),
        );
    }
}
