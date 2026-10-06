<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Composer;
use Composer\Package\CompletePackage;
use Composer\Package\CompletePackageInterface;
use Composer\Pcre\Preg;
use Composer\Repository\PlatformRepository;
use Composer\Semver\VersionParser;
use Php\Pie\ExtensionName;
use Php\Pie\Platform\InstalledPiePackages;
use Php\Pie\Platform\PkgConfig;
use Php\Pie\Platform\TargetPhp\PhpBinaryPath;
use Php\Pie\Platform\TargetPlatform;
use UnexpectedValueException;

use function array_key_exists;
use function array_map;
use function array_values;
use function in_array;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class PhpBinaryPathBasedPlatformRepository extends PlatformRepository
{
    /**
     * The key is the name of the dependency in `composer.json`, but without
     * the `lib-` prefix; e.g. `curl` would be `lib-curl` in the
     * `composer.json`. The value is the name of the library to look up using
     * `pkg-config`.
     */
    private const PKG_CONFIG_LIBRARIES = [
        'curl' => 'libcurl',
        'enchant' => 'enchant',
        'enchant-2' => 'enchant-2',
        'sodium' => 'libsodium',
        'ffi' => 'libffi',
        'xslt' => 'libxslt',
        'zip' => 'libzip',
        'png' => 'libpng',
        'avif' => 'libavif',
        'webp' => 'libwebp',
        'jpeg' => 'libjpeg',
        'xpm' => 'xpm',
        'freetype2' => 'freetype2',
        'gdlib' => 'gdlib',
        'gmp' => 'gmp',
        'gpgme' => 'gpgme',
        'pam' => 'pam',
        'sasl' => 'libsasl2',
        'onig' => 'oniguruma',
        'odbc' => 'libiodbc',
        'capstone' => 'capstone',
        'pcre' => 'libpcre2-8',
        'edit' => 'libedit',
        'snmp' => 'netsnmp',
        'argon2' => 'libargon2',
        'uriparser' => 'liburiparser',
        'exslt' => 'libexslt',
    ];

    private VersionParser $versionParser;

    /** @param list<ExtensionName> $extensionsBeingInstalled */
    public function __construct(PhpBinaryPath $phpBinaryPath, Composer $composer, InstalledPiePackages $installedPiePackages, PkgConfig $pkgConfig, array $extensionsBeingInstalled)
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
        foreach ($piePackages->packages() as $piePackage) {
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

        $extensionNamesBeingInstalled = array_map(static fn (ExtensionName $ext) => $ext->name(), $extensionsBeingInstalled);

        foreach ($extVersions as $extension => $extensionVersion) {
            /**
             * If the extension we're trying to exclude is not excluded from this list if it is already installed
             * and enabled, it conflicts when running {@see ComposerIntegrationHandler}.
             *
             * @link https://github.com/php/pie/issues/150
             */
            if (in_array($extension, $extensionNamesBeingInstalled, true)) {
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

        $this->addLibrariesUsingPkgConfig($pkgConfig);

        parent::__construct();
    }

    public static function forTargetPlatform(TargetPlatform $targetPlatform, Composer $composer): self
    {
        return new self($targetPlatform->phpBinaryPath, $composer, new InstalledPiePackages(), PkgConfig::detect(), []);
    }

    private function packageForExtension(string $name, string $prettyVersion): CompletePackageInterface
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

    /**
     * Instructions for PIE to install these libraries, if they are missing, should be added
     * into {@see \Php\Pie\DependencyResolver\DependencyInstaller\SystemDependenciesDefinition::default()}
     */
    private function addLibrariesUsingPkgConfig(PkgConfig $pkgConfig): void
    {
        $libraryVersions = $pkgConfig->versionsOf(array_values(self::PKG_CONFIG_LIBRARIES));

        foreach (self::PKG_CONFIG_LIBRARIES as $alias => $library) {
            if (! array_key_exists($library, $libraryVersions)) {
                continue;
            }

            $prettyVersion = $libraryVersions[$library];

            try {
                $version = $this->versionParser->normalize($prettyVersion);
            } catch (UnexpectedValueException) {
                $version = '*'; // @todo check this is the best way to handle unparsed versions?
            }

            $lib = new CompletePackage('lib-' . $alias, $version, $prettyVersion);
            $lib->setDescription('The ' . $alias . ' library, ' . $library);
            $this->addPackage($lib);
        }
    }
}
