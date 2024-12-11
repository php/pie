<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Package\CompletePackage;
use Composer\Pcre\Preg;
use Composer\Repository\PlatformRepository;
use Composer\Semver\VersionParser;
use Php\Pie\ExtensionName;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use UnexpectedValueException;

use function str_replace;
use function strtolower;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class PhpBinaryPathBasedPlatformRepository extends PlatformRepository
{
    private VersionParser $versionParser;

    public function __construct(PhpBinaryPath $phpBinaryPath, ExtensionName|null $extensionBeingInstalled)
    {
        $this->versionParser = new VersionParser();
        $this->packages      = [];

        $phpVersion = $phpBinaryPath->version();
        $php        = new CompletePackage('php', $this->versionParser->normalize($phpVersion), $phpVersion);
        $php->setDescription('The PHP interpreter');
        $this->addPackage($php);

        $extVersions = $phpBinaryPath->extensions();

        foreach ($extVersions as $extension => $extensionVersion) {
            /**
             * If the extension we're trying to exclude is not excluded from this list if it is already installed
             * and enabled, it conflicts when running {@see ComposerIntegrationHandler}.
             *
             * @link https://github.com/php/pie/issues/150
             */
            if ($extensionBeingInstalled !== null && $extension === $extensionBeingInstalled->name()) {
                continue;
            }

            $this->addPackage($this->packageForExtension($extension, $extensionVersion));
        }

        parent::__construct();
    }

    private function packageForExtension(string $name, string $prettyVersion): CompletePackage
    {
        $extraDescription = '';

        try {
            $version = $this->versionParser->normalize($prettyVersion);
        } catch (UnexpectedValueException) {
            $extraDescription = ' (actual version: ' . $prettyVersion . ')';
            if (Preg::isMatchStrictGroups('{^(\d+\.\d+\.\d+(?:\.\d+)?)}', $prettyVersion, $match)) {
                $prettyVersion = $match[1];
            } else {
                $prettyVersion = '0';
            }

            $version = $this->versionParser->normalize($prettyVersion);
        }

        $package = new CompletePackage(
            'ext-' . str_replace(' ', '-', strtolower($name)),
            $version,
            $prettyVersion,
        );
        $package->setDescription('The ' . $name . ' PHP extension' . $extraDescription);
        $package->setType('php-ext');

        return $package;
    }
}
