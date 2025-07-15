<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\Package;
use Composer\Package\Version\VersionParser;
use Composer\Repository\ArrayRepository;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\OperatingSystemFamily;
use Php\Pie\Platform\TargetPlatform;

use function array_combine;
use function array_key_exists;
use function array_keys;
use function array_map;
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
        ['name' => 'calendar'],
        ['name' => 'ctype'],
        ['name' => 'curl'],
        ['name' => 'dba'],
        ['name' => 'dom'],
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
        ],
        // ['name' => 'odbc'], // build failure - cp: cannot stat '/usr/local/lib/odbclib.a': No such file or directory configure: error: ODBC header file '/usr/local/incl/sqlext.h' not found!
        [
            'name' => 'opcache',
            'type' => ExtensionType::ZendExtension,
            'require' => ['php' => '>= 5.5.0'],
        ],
//        ['name' => 'openssl'], // Not building in CI
        ['name' => 'pcntl'],
        // ['name' => 'pdo', 'require' => ['php' => '>= 5.1.0']], // build failure - make: *** [Makefile:206: /home/james/.config/pie/php8.4_64f029c38a947437b5385bfed58650fb/vendor/php/pdo/ext/pdo/pdo_sql_parser.c] Error 127
        // ['name' => 'pdo_dblib', 'require' => ['php' => '>= 5.1.0']], // build failure - configure: error: Cannot find FreeTDS in known installation directories.
        // ['name' => 'pdo_firebird', 'require' => ['php' => '>= 5.1.0']], // build failure - configure: error: libfbclient not found.
//        [
//            'name' => 'pdo_mysql',
//            'require' => ['php' => '>= 5.1.0'],
//        ], // Not building in CI
        // ['name' => 'pdo_odbc', 'require' => ['php' => '>= 5.1.0']], // build failure - configure: error: Unknown ODBC flavour yes
//        [
//            'name' => 'pdo_pgsql',
//            'require' => ['php' => '>= 5.1.0'],
//        ], // Not building in CI
//        [
//            'name' => 'pdo_sqlite',
//            'require' => ['php' => '>= 5.1.0'],
//        ], // Not building in CI
        ['name' => 'pgsql'],
        // ['name' => 'phar', 'require' => ['php' => '>= 5.3.0']], // build failure - config.status: error: cannot find input file: '/phar.1.in'
        ['name' => 'posix'],
        ['name' => 'readline'],
        ['name' => 'session'],
        ['name' => 'shmop'],
        ['name' => 'simplexml'],
        ['name' => 'snmp'],
        ['name' => 'soap'],
        ['name' => 'sockets'],
        [
            'name' => 'sodium',
            'require' => ['php' => '>= 7.2.0'],
        ],
        [
            'name' => 'sqlite3',
            'require' => ['php' => '>= 5.3.0'],
        ],
        ['name' => 'sysvmsg'],
        ['name' => 'sysvsem'],
        ['name' => 'sysvshm'],
        ['name' => 'tidy'],
        // ['name' => 'tokenizer'], // build failure - make: *** No rule to make target '/home/james/workspace/oss/php-src/ext/tokenizer/Zend/zend_language_parser.y', needed by '/home/james/workspace/oss/php-src/ext/tokenizer/Zend/zend_language_parser.c'. Stop.
        ['name' => 'xml'],
//        [
//            'name' => 'xmlreader',
//            'require' => [
//                'php' => '>= 5.1.0',
//                'ext-xml' => '*',
//                'ext-dom' => '*',
//            ],
//        ], // Not building in CI
        [
            'name' => 'xmlwriter',
            'require' => [
                'php' => '>= 5.2.0',
                'ext-xml' => '*',
            ],
        ],
        ['name' => 'xsl'],
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
}
