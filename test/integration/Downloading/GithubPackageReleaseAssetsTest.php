<?php

declare(strict_types=1);

namespace Php\PieIntegrationTest\Downloading;

use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Package\CompletePackageInterface;
use Composer\Util\HttpDownloader;
use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadUrlMethod;
use Php\Pie\Downloading\GithubPackageReleaseAssets;
use Php\Pie\Downloading\ReleaseAsset;
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
            null,
        );

        $package = new Package(
            $this->createMock(CompletePackageInterface::class),
            ExtensionType::PhpModule,
            ExtensionName::normaliseFromString('example_pie_extension'),
            'asgrim/example-pie-extension',
            '2.0.2',
            'https://api.github.com/repos/asgrim/example-pie-extension/zipball/f9ed13ea95dada34c6cc5a052da258dbda059d27',
        );

        $io     = new NullIO();
        $config = Factory::createConfig();
        $io->loadConfiguration($config);

        self::assertEquals(
            new ReleaseAsset(
                'https://api.github.com/repos/asgrim/example-pie-extension/releases/assets/197867674',
                'php_example_pie_extension-2.0.2-8.3-ts-vs16-x86_64.zip',
                ['Accept: application/octet-stream'],
            ),
            (new GithubPackageReleaseAssets('https://api.github.com'))
                ->findMatchingReleaseAsset(
                    $targetPlatform,
                    $package,
                    new HttpDownloader($io, $config),
                    DownloadUrlMethod::WindowsBinaryDownload,
                    WindowsExtensionAssetName::zipNames(
                        $targetPlatform,
                        $package,
                    ),
                ),
        );
    }
}
