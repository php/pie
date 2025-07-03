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

use function array_key_exists;
use function array_map;
use function sprintf;

class BundledPhpExtensionsRepository extends ArrayRepository
{
    /**
     * @var list<array{
     *     name: non-empty-string,
     *     version?: non-empty-string,
     *     os-families?: non-empty-list<OperatingSystemFamily>,
     *     type?: ExtensionType,
     *     priority?: int,
     * }>
     */
    private static array $bundledPhpExtensions = [
        ['name' => 'bcmath'],
        ['name' => 'bz2'],
        ['name' => 'calendar'],
        [
            'name' => 'com_dotnet',
            'os-families' => [OperatingSystemFamily::Windows],
        ],
        ['name' => 'ctype'],
        ['name' => 'curl'],
        ['name' => 'dba'],
        ['name' => 'dl_test', 'version' => '>= 8.2.0'],
        ['name' => 'dom'],
        ['name' => 'enchant', 'version' => '>= 5.2.0'],
        ['name' => 'exif'],
        ['name' => 'ffi', 'version' => '>= 7.4.0'],
        ['name' => 'gd'],
        ['name' => 'gettext'],
        ['name' => 'gmp'],
        ['name' => 'iconv'],
        ['name' => 'intl', 'version' => '>= 5.3.0'],
        ['name' => 'ldap'],
        ['name' => 'lexbor', 'version' => '>= 8.5.0'],
        ['name' => 'mbstring'],
        [
            'name' => 'mysqli',
            'priority' => 90, // must load after mysqlnd
        ],
        ['name' => 'mysqlnd', 'version' => '>= 5.3.0'],
        ['name' => 'odbc'], // build failure - cp: cannot stat '/usr/local/lib/odbclib.a': No such file or directory configure: error: ODBC header file '/usr/local/incl/sqlext.h' not found!
        [
            'name' => 'opcache',
            'type' => ExtensionType::ZendExtension,
            'version' => '>= 5.5.0',
        ],
        ['name' => 'openssl'],
        ['name' => 'pcntl'],
        // ['name' => 'pdo', 'version' => '>= 5.1.0'], // build failure - make: *** [Makefile:206: /home/james/.config/pie/php8.4_64f029c38a947437b5385bfed58650fb/vendor/php/pdo/ext/pdo/pdo_sql_parser.c] Error 127
        // ['name' => 'pdo_dblib', 'version' => '>= 5.1.0'], // build failure - configure: error: Cannot find FreeTDS in known installation directories.
        // ['name' => 'pdo_firebird', 'version' => '>= 5.1.0'], // build failure - configure: error: libfbclient not found.
        ['name' => 'pdo_mysql', 'version' => '>= 5.1.0'], // build failure - make: *** [Makefile:207: /home/james/.config/pie/php8.4_64f029c38a947437b5385bfed58650fb/vendor/php/pdo_mysql/ext/pdo_mysql/mysql_sql_parser.c] Error 127
        // ['name' => 'pdo_odbc', 'version' => '>= 5.1.0'], // build failure - configure: error: Unknown ODBC flavour yes
        // ['name' => 'pdo_pgsql', 'version' => '>= 5.1.0'], // build failure - make: *** [Makefile:207: /home/james/.config/pie/php8.4_64f029c38a947437b5385bfed58650fb/vendor/php/pdo_pgsql/ext/pdo_pgsql/pgsql_sql_parser.c] Error 127
        // ['name' => 'pdo_sqlite', 'version' => '>= 5.1.0'], // build failure - make: *** [Makefile:207: /home/james/.config/pie/php8.4_64f029c38a947437b5385bfed58650fb/vendor/php/pdo_sqlite/ext/pdo_sqlite/sqlite_sql_parser.c] Error 127
        ['name' => 'pgsql'],
        // ['name' => 'phar', 'version' => '>= 5.3.0'], // build failure - config.status: error: cannot find input file: '/phar.1.in'
        ['name' => 'posix'],
        ['name' => 'readline'],
        ['name' => 'session'],
        ['name' => 'shmop'],
        ['name' => 'simplexml'],
        ['name' => 'snmp'],
        ['name' => 'soap'],
        ['name' => 'sockets'],
        ['name' => 'sodium', 'version' => '>= 7.2.0'],
        ['name' => 'sqlite3', 'version' => '>= 5.3.0'],
        ['name' => 'sysvmsg'],
        ['name' => 'sysvsem'],
        ['name' => 'sysvshm'],
        ['name' => 'tidy'],
        // ['name' => 'tokenizer'], // build failure - make: *** No rule to make target '/home/james/workspace/oss/php-src/ext/tokenizer/Zend/zend_language_parser.y', needed by '/home/james/workspace/oss/php-src/ext/tokenizer/Zend/zend_language_parser.c'. Stop.
        ['name' => 'uri', 'version' => '>= 8.5.0'],
        ['name' => 'xml'],
        ['name' => 'xmlreader', 'version' => '>= 5.1.0'],
        ['name' => 'xmlwriter', 'version' => '>= 5.2.0'],
        ['name' => 'xsl'],
        // ['name' => 'zend_test', 'version' => '>= 7.2.0'], // build failure - ext/zend_test/test.c:48:11: fatal error: libxml/globals.h: No such file or directory
        ['name' => 'zip', 'version' => '>= 5.2.0'],
        ['name' => 'zlib'],
    ];

    public static function forTargetPlatform(TargetPlatform $targetPlatform): self
    {
        $versionParser = new VersionParser();
        $phpVersion    = $targetPlatform->phpBinaryPath->version();

        return new self(array_map(
            static function (array $extension) use ($versionParser, $phpVersion): Package {
                if (! array_key_exists('version', $extension)) {
                    $extension['version'] = $phpVersion;
                }

                $package = new CompletePackage('php/' . $extension['name'], $phpVersion . '.0', $phpVersion);
                $package->setType(($extension['type'] ?? ExtensionType::PhpModule)->value);
                $package->setDistType('zip');
                $package->setRequires([
                    'php' => new Link(
                        'php/' . $extension['name'],
                        'php',
                        $versionParser->parseConstraints($extension['version']),
                        'requires',
                        $extension['version'],
                    ),
                ]);
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
