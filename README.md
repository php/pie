# 🥧 PIE (PHP Installer for Extensions)

You will need PHP 8.1 or newer to run PIE, but PIE can install an extension to
any installed PHP version.

If you are an extension maintainer wanting to add PIE support to your extension,
please read [extension-maintainers](./docs/extension-maintainers.md).

## Installing an extension using PIE

You can install an extension using the `install` command. For example, to
install the `example_pie_extension` extension, you would run:

```shell
$ bin/pie install asgrim/example-pie-extension
This command may need elevated privileges, and may prompt you for your password.
You are running PHP 8.3.10
Target PHP installation: 8.3.10 nts, on Linux/OSX/etc x86_64 (from /usr/bin/php8.3)
Found package: asgrim/example-pie-extension:1.0.1 which provides ext-example_pie_extension
phpize complete.
Configure complete.
Build complete: /tmp/pie_downloader_66e0b1de73cdb6.04069773/asgrim-example-pie-extension-769f906/modules/example_pie_extension.so
Install complete: /usr/lib/php/20230831/example_pie_extension.so
You must now add "extension=example_pie_extension" to your php.ini
$
```

### Using PIE to install an extension for a different PHP version

If you are trying to install an extension for a different version of PHP, you
may specify this on non-Windows systems with the `--with-php-config` option:

```shell
bin/pie install --with-php-config=/usr/bin/php-config7.2 my/extension
```

On Windows, you may provide a path to the `php` executable itself using the
`--with-php-path` option. This is an example on Windows where PHP 8.1 is used
to run PIE, but we want to download the extension for PHP 8.3:

```shell
> C:\php-8.3.6\php.exe bin/pie install --with-php-path=C:\php-8.1.7\php.exe asgrim/example-pie-extension
```

## Extensions that support PIE

A list of extensions that support PIE can be found on
[https://packagist.org/extensions](https://packagist.org/extensions).
