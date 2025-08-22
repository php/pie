<?php

declare(strict_types=1);

namespace Php\Pie\Downloading\Exception;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\Downloading\DownloadUrlMethod;
use Php\Pie\Platform\TargetPlatform;
use RuntimeException;

use function implode;
use function sprintf;

use const PHP_EOL;

class CouldNotFindReleaseAsset extends RuntimeException
{
    /** @param non-empty-list<non-empty-string> $expectedAssetNames */
    public static function forPackage(TargetPlatform $targetPlatform, Package $package, array $expectedAssetNames): self
    {
        $downloadUrlMethod = DownloadUrlMethod::fromPackage($package, $targetPlatform);

        if ($downloadUrlMethod === DownloadUrlMethod::WindowsBinaryDownload) {
            return new self(sprintf(
                'Windows archive with prebuilt extension for %s was not attached on release %s - looked for one of "%s"',
                $package->name(),
                $package->version(),
                implode(', ', $expectedAssetNames),
            ));
        }

        return new self(sprintf(
            'Could not find release asset for %s named one of "%s"',
            $package->prettyNameAndVersion(),
            implode(', ', $expectedAssetNames),
        ));
    }

    public static function forPackageWithMissingTag(Package $package): self
    {
        if (
            $package->downloadUrlMethod() === DownloadUrlMethod::PrePackagedSourceDownload
            && $package->composerPackage()->isDev()
        ) {
            return new self(sprintf(
                'The package %s uses pre-packaged source archives, which are not available for branch aliases such as %s. You should either omit the version constraint to use the latest compatible version, or use a tagged version instead. You can find a list of tagged versions on:%shttps://packagist.org/packages/%s',
                $package->name(),
                $package->version(),
                PHP_EOL . PHP_EOL,
                $package->name(),
            ));
        }

        return new self(sprintf(
            'Could not find release by tag name for %s',
            $package->prettyNameAndVersion(),
        ));
    }

    public static function forMissingWindowsCompiler(TargetPlatform $targetPlatform): self
    {
        return new self(sprintf(
            'Could not determine Windows Compiler for PHP %s on %s',
            $targetPlatform->phpBinaryPath->version(),
            $targetPlatform->operatingSystem->name,
        ));
    }
}
