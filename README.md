# 🥧 PIE (PHP Installer for Extensions)

You will need PHP 8.1 or newer to run PIE, but PIE can install an extension to any installed PHP version.

## Installing

## Usage

You can download an extension ready to be built or installed using the `download` command. For example, to download the
`example_pie_extension` extension, you would run:

```shell
$ bin/pie download asgrim/example-pie-extension
You are running PHP 8.3.7
Target PHP installation: 8.3.7 nts, on Linux/OSX/etc x86_64 (from /usr/bin/php8.3)
Found package: asgrim/example-pie-extension:1.0.1 which provides ext-example_pie_extension
Extracted asgrim/example-pie-extension:1.0.1 source to: /tmp/pie_downloader_6645f07a28bec9.66045489/asgrim-example-pie-extension-769f906
$ 
```

If you are trying to install an extension for a different version of PHP, you may specify this on non-Windows systems
with the `--with-php-config` option like:

```shell
bin/pie download --with-php-config=/usr/bin/php-config7.2 my/extension
```

On all platforms, you may provide a path to the `php` executable itself using the `--with-php-path` option. This is an
example on Windows where PHP 8.1 is used to run PIE, but we want to download the extension for PHP 8.3:

```shell
> C:\php-8.1.7\php.exe bin/pie download --with-php-path=C:\php-8.3.6\php.exe asgrim/example-pie-extension
You are running PHP 8.1.7
Target PHP installation: 8.3.6 ts, vs16, on Windows x86_64 (from C:\php-8.3.6\php.exe)
Found package: asgrim/example-pie-extension:1.0.1 which provides ext-example_pie_extension
Extracted asgrim/example-pie-extension:1.0.1 source to: C:\path\to\temp\pie_downloader_66547faa7db3d7.06129230
```

And this is a very similar example (using PHP 8.1 to run PIE to download a PHP 8.3 extension) on a non-Windows platform:

```shell
$ php8.1 bin/pie download --with-php-path=/usr/bin/php8.3 asgrim/example-pie-extension
You are running PHP 8.1.28
Target PHP installation: 8.3.7 nts, on Linux/OSX/etc x86_64 (from /usr/bin/php8.3)
Found package: asgrim/example-pie-extension:1.0.1 which provides ext-example_pie_extension
Extracted asgrim/example-pie-extension:1.0.1 source to: /tmp/pie_downloader_66547da1e6c685.25242810/asgrim-example-pie-extension-769f906
```

## Developing

### Testing

### Building and Deploying
