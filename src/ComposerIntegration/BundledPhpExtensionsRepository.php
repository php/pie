<?php

declare(strict_types=1);

namespace Php\Pie\ComposerIntegration;

use Composer\Package\CompletePackage;
use Composer\Package\Package;
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
     * @todo add version constraints
     * @var list<array{
     *     name: non-empty-string,
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
        ['name' => 'dl_test'],
        ['name' => 'dom'],
        ['name' => 'enchant'],
        ['name' => 'exif'],
        ['name' => 'ffi'], // @todo ext name mismatch
        ['name' => 'gd'],
        ['name' => 'gettext'],
        ['name' => 'gmp'],
        ['name' => 'iconv'],
        ['name' => 'intl'],
        ['name' => 'ldap'],
        // ['name' => 'lexbor'], // recent split it seems
        ['name' => 'mbstring'],
        [
            'name' => 'mysqli',
            'priority' => 90, // must load after mysqlnd
        ],
        ['name' => 'mysqlnd'],
        // ['name' => 'odbc'], // build failure
        [
            'name' => 'opcache', // @todo ext name mismatch
            'type' => ExtensionType::ZendExtension,
        ],
        ['name' => 'openssl'],
        ['name' => 'pcntl'],
        // ['name' => 'pdo'], // build failure
        // ['name' => 'pdo_dblib'], // build failure
        // ['name' => 'pdo_firebird'], // build failure
        // ['name' => 'pdo_mysql'], // build failure
        // ['name' => 'pdo_odbc'], // build failure
        // ['name' => 'pdo_pgsql'], // build failure
        // ['name' => 'pdo_sqlite'], // build failure
        // ['name' => 'pgsql'], // build failure
        // ['name' => 'phar'], // build failure
        ['name' => 'posix'],
        ['name' => 'readline'],
        ['name' => 'session'],
        ['name' => 'shmop'],
        ['name' => 'simplexml'], // @todo ext name mismatch
        ['name' => 'snmp'],
        ['name' => 'soap'],
        ['name' => 'sockets'],
        ['name' => 'sodium'],
        ['name' => 'sqlite3'],
        ['name' => 'sysvmsg'],
        ['name' => 'sysvsem'],
        ['name' => 'sysvshm'],
        ['name' => 'tidy'],
        // ['name' => 'tokenizer'], // build failure - make: *** No rule to make target '/home/james/workspace/oss/php-src/ext/tokenizer/Zend/zend_language_parser.y', needed by '/home/james/workspace/oss/php-src/ext/tokenizer/Zend/zend_language_parser.c'. Stop.
        // ['name' => 'uri'], // new ext, need to apply version constraint
        ['name' => 'xml'],
        ['name' => 'xmlreader'],
        ['name' => 'xmlwriter'],
        ['name' => 'xsl'],
        // ['name' => 'zend_test'], // build failure - ext/zend_test/test.c:48:11: fatal error: libxml/globals.h: No such file or directory
        ['name' => 'zip'],
        ['name' => 'zlib'],
    ];

    public static function forTargetPlatform(TargetPlatform $targetPlatform): self
    {
        $phpVersion = $targetPlatform->phpBinaryPath->version();

        return new self(array_map(
            static function (array $extension) use ($phpVersion): Package {
                $package = new CompletePackage('php/' . $extension['name'], $phpVersion . '.0', $phpVersion);
                $package->setType(($extension['type'] ?? ExtensionType::PhpModule)->value);
                $package->setDistType('zip');
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
