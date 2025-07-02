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
        // ['name' => 'mysqli'], // build failure
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
        // ['name' => 'tokenizer'], // build failure
        // ['name' => 'uri'], // new ext, need to apply version constraint
        ['name' => 'xml'],
        ['name' => 'xmlreader'],
        ['name' => 'xmlwriter'],
        ['name' => 'xsl'],
        // ['name' => 'zend_test'], // build failure
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

                $package->setPhpExt($phpExt);

                return $package;
            },
            self::$bundledPhpExtensions,
        ));
    }
}
