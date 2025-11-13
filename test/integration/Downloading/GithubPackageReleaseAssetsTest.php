<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Downloading;

use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Package\CompletePackage;
use Composer\Util\AuthHelper;
use Composer\Util\HttpDownloader;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\GithubPackageReleaseAssets;
use Php\Pie\ExtensionName;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\Architecture;
use Php\Pie\Platform\OperatingSystem;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Platform\ThreadSafetyMode;
use Php\Pie\Platform\WindowsCompiler;
use Php\Pie\Platform\WindowsExtensionAssetName;
use Php\Pie\Util\PieComposerAuthHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;

#[CoversClass(GithubPackageReleaseAssets::class)]
final class GithubPackageReleaseAssetsTest extends TestCase
{
    #[RequiresOperatingSystemFamily('Windows')]
    public function testDeterminingReleaseAssetUrlForWindows(): void
    {
        $phpBinaryPath = $this->createMock(PhpBinaryPath::class);
        $phpBinaryPath->expects(self::any())
            ->method('majorMinorVersion')
            ->willReturn('8.3');

        $targetPlatform = new TargetPlatform(
            OperatingSystem::Windows,
            OperatingSystemFamily::Windows,
            $phpBinaryPath,
            Architecture::x86_64,
            ThreadSafetyMode::ThreadSafe,
            1,
            WindowsCompiler::VS16,
        );

        $package = new Package(
            $this->createMock(CompletePackage::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('example_pie_extension'),
            'asgrim/example-pie-extension',
            '2.0.2',
            'https://api.github.com/repos/asgrim/example-pie-extension/zipball/f9ed13ea95dada34c6cc5a052da258dbda059d27',
        );

        $io     = new NullIO();
        $config = Factory::createConfig();
        $io->loadConfiguration($config);

        self::assertSame(
            'https://github.com/asgrim/example-pie-extension/releases/download/2.0.2/php_example_pie_extension-2.0.2-8.3-ts-vs16-x86_64.zip',
            (new GithubPackageReleaseAssets('https://api.github.com'))
                ->findMatchingReleaseAssetUrl(
                    $targetPlatform,
                    $package,
                    new PieComposerAuthHelper(new AuthHelper($io, $config)),
                    new HttpDownloader($io, $config),
                    WindowsExtensionAssetName::zipNames(
                        $targetPlatform,
                        $package,
                    ),
                ),
        );
    }
}
