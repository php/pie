<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver\DependencyInstaller;

use Php\Pie\Platform\PackageManager;

class SystemDependenciesDefinition
{
    /** @param array<non-empty-string, array<non-empty-string, non-empty-string>> $definition */
    public function __construct(public readonly array $definition)
    {
    }

    /**
     * Checks for the existence of these libraries should be added into
     * {@see \Php\Pie\ComposerIntegration\PhpBinaryPathBasedPlatformRepository::addLibrariesUsingPkgConfig()}
     */
    public static function default(): self
    {
        return new self([
            'sodium' => [
                PackageManager::Apt->value => 'libsodium-dev',
                PackageManager::Apk->value => 'libsodium-dev',
                PackageManager::Dnf->value => 'pkgconfig(libsodium)',
                PackageManager::Microdnf->value => 'pkgconfig(libsodium)',
                PackageManager::Yum->value => 'pkgconfig(libsodium)',
            ],
            'jpeg' => [
                PackageManager::Apt->value => 'libjpeg-dev',
                PackageManager::Apk->value => 'libjpeg-turbo-dev',
                PackageManager::Dnf->value => 'pkgconfig(libjpeg)',
                PackageManager::Microdnf->value => 'pkgconfig(libjpeg)',
                PackageManager::Yum->value => 'pkgconfig(libjpeg)',
            ],
            'zip' => [
                PackageManager::Apt->value => 'libzip-dev',
                PackageManager::Apk->value => 'libzip-dev',
                PackageManager::Dnf->value => 'pkgconfig(libzip)',
                PackageManager::Microdnf->value => 'pkgconfig(libzip)',
                PackageManager::Yum->value => 'pkgconfig(libzip)',
            ],
            'xslt' => [
                PackageManager::Apt->value => 'libxslt1-dev',
                PackageManager::Apk->value => 'libxslt-dev',
                PackageManager::Dnf->value => 'pkgconfig(libxslt)',
                PackageManager::Microdnf->value => 'pkgconfig(libxslt)',
                PackageManager::Yum->value => 'pkgconfig(libxslt)',
            ],
            'ffi' => [
                PackageManager::Apt->value => 'libffi-dev',
                PackageManager::Apk->value => 'libffi-dev',
                PackageManager::Dnf->value => 'pkgconfig(libffi)',
                PackageManager::Microdnf->value => 'pkgconfig(libffi)',
                PackageManager::Yum->value => 'pkgconfig(libffi)',
            ],
            'curl' => [
                PackageManager::Apt->value => 'libcurl4-openssl-dev',
                PackageManager::Apk->value => 'curl-dev',
                PackageManager::Dnf->value => 'pkgconfig(libcurl)',
                PackageManager::Microdnf->value => 'pkgconfig(libcurl)',
                PackageManager::Yum->value => 'pkgconfig(libcurl)',
            ],
            'gpgme' => [
                PackageManager::Apt->value => 'libgpgme-dev',
                PackageManager::Apk->value => 'gpgme-dev',
                PackageManager::Dnf->value => 'pkgconfig(gpgme)',
                PackageManager::Microdnf->value => 'pkgconfig(gpgme)',
                PackageManager::Yum->value => 'pkgconfig(gpgme)',
            ],
            'pam' => [
                PackageManager::Apt->value => 'libpam0g-dev',
                PackageManager::Apk->value => 'linux-pam-dev',
                PackageManager::Dnf->value => 'pkgconfig(pam)',
                PackageManager::Microdnf->value => 'pkgconfig(pam)',
                PackageManager::Yum->value => 'pkgconfig(pam)',
            ],
        ]);
    }
}
