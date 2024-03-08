<?php

declare(strict_types=1);

namespace Php\Pie\Downloading;

use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Util\AuthHelper;
use GuzzleHttp\Client;
use Php\Pie\DependencyResolver\Package;

use function file_exists;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class UnixDownloadAndExtract implements DownloadAndExtract
{
    public function __construct(
        private readonly DownloadZip $downloadZip,
        private readonly ExtractZip $extractZip,
    ) {
    }

    public static function factory(): self
    {
        $config = Factory::createConfig();
        $io     = new NullIO();
        $io->loadConfiguration($config);

        return new self(
            new DownloadZip(
                new Client(),
                new AuthHelper($io, $config),
            ),
            new ExtractZip(),
        );
    }

    public function __invoke(Package $package): DownloadedPackage
    {
        $localTempPath = sys_get_temp_dir() . '/' . uniqid('pie_downloader_', true);
        if (! file_exists($localTempPath)) {
            mkdir($localTempPath, recursive: true);
        }

        $tmpZipFile = $this->downloadZip->downloadZipAndReturnLocalPath($package, $localTempPath);

        $extractedPath = $this->extractZip->to($tmpZipFile, $localTempPath);

        return DownloadedPackage::fromPackageAndExtractedPath($package, $extractedPath);
    }
}
