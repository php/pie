<?php

declare(strict_types=1);

namespace Php\Pie\Downloading\Exception;

use Php\Pie\DependencyResolver\Package;
use Php\Pie\Platform\TargetPlatform;
use RuntimeException;

use function implode;
use function sprintf;

class CouldNotFindReleaseAsset extends RuntimeException
{
    /** @param non-empty-list<non-empty-string> $expectedAssetNames */
    public static function forPackage(Package $package, array $expectedAssetNames): self
    {
        return new self(sprintf(
            'Could not find release asset for %s named one of "%s"',
            $package->prettyNameAndVersion(),
            implode(', ', $expectedAssetNames),
        ));
    }

    public static function forPackageWithMissingTag(Package $package): self
    {
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
