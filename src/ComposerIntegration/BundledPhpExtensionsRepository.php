<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\Package;
use Composer\Package\Version\VersionParser;
use Composer\Repository\ArrayRepository;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPlatform;
use Php\Pie\Util\Process;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function array_combine;
use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function in_array;
use function realpath;
use function sprintf;

class BundledPhpExtensionsRepository extends ArrayRepository
{
    /**
     * @var list<array{
     *     name: non-empty-string,
     *     require?: array<string, non-empty-string>,
     *     os-families?: non-empty-list<OperatingSystemFamily>,
     *     type?: ExtensionType,
     *     priority?: int,
     * }>
     */
    private static array $bundledPhpExtensions = [
        ['name' => 'bcmath'],
        ['name' => 'bz2'],
//            'require' => ['lib-bz2' => '*'], libbz2-dev does not provide a bzip2.pc for pkg-config ...
        ['name' => 'calendar'],
        ['name' => 'ctype'],
        [
            'name' => 'curl',
            'require' => ['lib-curl' => '*'],
        ],
        ['name' => 'dba'],
        [
            'name' => 'dom',
            'require' => [
                'php' => '>= 5.2.0',
                'ext-libxml' => '*',
            ],
        ],
        [
            'name' => 'enchant',
            'require' => ['php' => '>= 5.2.0'],
        ],
        ['name' => 'exif'],
        [
            'name' => 'ffi',
            'require' => ['php' => '>= 7.4.0'],
        ],
        // ['name' => 'gd'], // build failure - ext/gd/gd.c:79:11: fatal error: ft2build.h: No such file or directory
        ['name' => 'gettext'],
        ['name' => 'gmp'],
        ['name' => 'iconv'],
        [
            'name' => 'intl',
            'require' => ['php' => '>= 5.3.0'],
        ],
        ['name' => 'ldap'],
        ['name' => 'mbstring'],
        [
            'name' => 'mysqlnd',
            'require' => [
                'php' => '>= 5.3.0',
                'ext-openssl' => '*',
            ],
        ],
        [
            'name' => 'mysqli',
            'priority' => 90, // must load after mysqlnd
            'require' => [
                'php' => '>= 5.3.0',
                /**
                 * Note: Whilst mysqli can be built without mysqlnd (you could
                 * specify `--with-mysqli=...`, we have to make installation
                 * with PIE practical at least to start with. We can look at
                 * improving this later, but for now something is better than
                 * nothing :)
                 */
                'ext-mysqlnd' => '*',
            ],
        ],
        [
            'name' => 'opcache',
            'type' => ExtensionType::ZendExtension,
            'require' => ['php' => '>= 5.5.0, < 8.5.0'],
        ],
//        ['name' => 'openssl'], // Not building in CI
        ['name' => 'pcntl'],
        [
            'name' => 'pdo',
            'require' => ['php' => '>= 5.1.0'],
        ],
        [
            'name' => 'pdo_mysql',
            'require' => [
                'php' => '>= 5.1.0',
                'ext-pdo' => '*',
            ],
        ],
        [
            'name' => 'pdo_pgsql',
            'require' => [
                'php' => '>= 5.1.0',
                'ext-pdo' => '*',
            ],
        ],
        [
            'name' => 'pdo_sqlite',
            'require' => [
                'php' => '>= 5.1.0',
                'ext-pdo' => '*',
            ],
        ],
        ['name' => 'pgsql'],
        ['name' => 'posix'],
        ['name' => 'readline'],
        ['name' => 'session'],
        ['name' => 'shmop'],
        [
            'name' => 'simplexml',
            'require' => [
                'php' => '>= 5.2.0',
                'ext-libxml' => '*',
            ],
        ],
        ['name' => 'snmp'],
        [
            'name' => 'soap',
            'require' => [
                'php' => '>= 5.2.0',
                'ext-libxml' => '*',
            ],
        ],
        ['name' => 'sockets'],
        [
            'name' => 'sodium',
            'require' => [
                'php' => '>= 7.2.0',
                'lib-sodium' => '*',
            ],
        ],
        [
            'name' => 'sqlite3',
            'require' => ['php' => '>= 5.3.0'],
        ],
        ['name' => 'sysvmsg'],
        ['name' => 'sysvsem'],
        ['name' => 'sysvshm'],
        ['name' => 'tidy'],
        [
            'name' => 'xml',
            'require' => [
                'php' => '>= 5.2.0',
                'ext-libxml' => '*',
            ],
        ],
        [
            'name' => 'xmlreader',
            'require' => [
                'php' => '>= 5.1.0',
                'ext-libxml' => '*',
                'ext-dom' => '*',
            ],
        ],
        [
            'name' => 'xmlwriter',
            'require' => [
                'php' => '>= 5.2.0',
                'ext-libxml' => '*',
            ],
        ],
        [
            'name' => 'xsl',
            'require' => [
                'php' => '>= 5.2.0',
                'ext-libxml' => '*',
            ],
        ],
        [
            'name' => 'zip',
            'require' => ['php' => '>= 5.2.0'],
        ],
        ['name' => 'zlib'],
    ];

    public static function forTargetPlatform(TargetPlatform $targetPlatform): self
    {
        $versionParser = new VersionParser();
        $phpVersion    = $targetPlatform->phpBinaryPath->version();

        return new self(array_map(
            static function (array $extension) use ($versionParser, $phpVersion): Package {
                if (! array_key_exists('require', $extension)) {
                    $extension['require'] = ['php' => $phpVersion];
                }

                $requireLinks = array_map(
                    static function (string $target, string $constraint) use ($extension, $versionParser): Link {
                        return new Link(
                            'php/' . $extension['name'],
                            $target,
                            $versionParser->parseConstraints($constraint),
                            'requires',
                            $constraint,
                        );
                    },
                    array_keys($extension['require']),
                    $extension['require'],
                );

                $package = new CompletePackage('php/' . $extension['name'], $phpVersion . '.0', $phpVersion);
                $package->setType(($extension['type'] ?? ExtensionType::PhpModule)->value);
                $package->setDistType('zip');
                $package->setRequires(array_combine(
                    array_map(static fn (Link $link) => $link->getTarget(), $requireLinks),
                    $requireLinks,
                ));
                $package->setDistUrl(sprintf('https://github.com/php/php-src/archive/refs/tags/php-%s.zip', $phpVersion));
                $package->setDistReference(sprintf('php-%s', $phpVersion));
                $phpExt = [
                    'extension-name' => $extension['name'],
                    'build-path' => 'ext/' . $extension['name'],
                ];

                if (array_key_exists('os-families', $extension)) {
                    $phpExt['os-families'] = array_map(
                        static fn (OperatingSystemFamily $osFamily) => $osFamily->value,
                        $extension['os-families'],
                    );
                }

                if (array_key_exists('priority', $extension)) {
                    $phpExt['priority'] = $extension['priority'];
                }

                $package->setPhpExt($phpExt);

                return $package;
            },
            self::$bundledPhpExtensions,
        ));
    }

    private static function findRe2c(): string
    {
        try {
            return Process::run(['which', 're2c']);
        } catch (ProcessFailedException $processFailed) {
            throw new RuntimeException('Unable to find re2c on the system', previous: $processFailed);
        }
    }

    /**
     * @param list<string> $makeCommand
     *
     * @return list<string>
     */
    public static function augmentMakeCommandForPhpBundledExtensions(array $makeCommand, DownloadedPackage $downloadedPackage): array
    {
        $extraCflags = [];
        if (
            in_array($downloadedPackage->package->name(), [
                'php/xmlreader',
                'php/dom',
            ])
        ) {
            $path = (string) realpath($downloadedPackage->extractedSourcePath . '/../..');
            if ($path !== '') {
                $extraCflags[] = '-I' . $path;
            }
        }

        if ($downloadedPackage->package->name() === 'php/dom') {
            $path = (string) realpath($downloadedPackage->extractedSourcePath . '/../../ext/lexbor');
            if ($path !== '') {
                $extraCflags[] = '-I' . $path;
            }
        }

        if (count($extraCflags)) {
            $makeCommand[] = 'EXTRA_CFLAGS=' . implode(' ', $extraCflags);
        }

        if (
            in_array($downloadedPackage->package->name(), [
                'php/pdo',
                'php/pdo_mysql',
                'php/pdo_pgsql',
                'php/pdo_sqlite',
            ])
        ) {
            $makeCommand[] = 'RE2C=' . self::findRe2c();
        }

        return $makeCommand;
    }
}
