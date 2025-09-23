<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Pcre\Preg;
use Composer\Repository\PlatformRepository;
use Composer\Semver\VersionParser;
use Php\Pie\ExtensionName;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Util\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use UnexpectedValueException;

use function explode;
use function in_array;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class PhpBinaryPathBasedPlatformRepository extends PlatformRepository
{
    private VersionParser $versionParser;

    public function __construct(PhpBinaryPath $phpBinaryPath, Composer $composer, InstalledPiePackages $installedPiePackages, ExtensionName|null $extensionBeingInstalled)
    {
        $this->versionParser = new VersionParser();
        $this->packages      = [];

        $phpVersion = $phpBinaryPath->version();
        $php        = new CompletePackage('php', $this->versionParser->normalize($phpVersion), $phpVersion);
        $php->setDescription('The PHP interpreter');
        $this->addPackage($php);

        $extVersions = $phpBinaryPath->extensions();

        $piePackages                          = $installedPiePackages->allPiePackages($composer);
        $extensionsBeingReplacedByPiePackages = [];
        foreach ($piePackages as $piePackage) {
            foreach ($piePackage->composerPackage()->getReplaces() as $replaceLink) {
                $target = $replaceLink->getTarget();
                if (
                    ! str_starts_with($target, 'ext-')
                    || ! ExtensionName::isValidExtensionName(substr($target, strlen('ext-')))
                ) {
                    continue;
                }

                $extensionsBeingReplacedByPiePackages[] = ExtensionName::normaliseFromString($replaceLink->getTarget())->name();
            }
        }

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

            /**
             * If any extensions present have `replaces`, we need to remove them otherwise it conflicts too
             *
             * @link https://github.com/php/pie/issues/161
             */
            if (in_array($extension, $extensionsBeingReplacedByPiePackages)) {
                continue;
            }

            $this->addPackage($this->packageForExtension($extension, $extensionVersion));
        }

        $this->addLibrariesUsingPkgConfig();

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

    private function detectLibraryWithPkgConfig(string $alias, string $library): void
    {
        try {
            $pkgConfigResult = Process::run(['pkg-config', '--print-provides', '--print-errors', $library]);
        } catch (ProcessFailedException) {
            return;
        }

        [$library, $prettyVersion] = explode('=', $pkgConfigResult);
        if (! $library || ! $prettyVersion) {
            return;
        }

        $version = $this->versionParser->normalize($prettyVersion);

        $lib = new CompletePackage('lib-' . $alias, $version, $prettyVersion);
        $lib->setDescription('The ' . $alias . ' library, ' . $library);
        $this->addPackage($lib);
    }

    private function addLibrariesUsingPkgConfig(): void
    {
        $this->detectLibraryWithPkgConfig('bz2', 'bzip2');
        $this->detectLibraryWithPkgConfig('curl', 'libcurl');
        $this->detectLibraryWithPkgConfig('sodium', 'libsodium');
    }
}
