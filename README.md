# 🥧 PIE (PHP Installer for Extensions)

## What is PIE?

PIE is the official installer for PHP extensions, which replaces
[PECL](https://pecl.php.net/) (which is now deprecated). PIE is distributed as a
[PHAR](https://www.php.net/manual/en/book.phar.php), just like Composer, and
works in a similar way to Composer, but it installs PHP extensions (PHP Modules
or Zend Extensions) to your PHP installation, rather than pulling PHP packages
into your project or library.

# Using PIE - what do I need to get started?

## Prerequisites

You will need PHP 8.1 or newer to run PIE, but PIE can install an extension to
any other installed PHP version.

On Linux/OSX, if any build tools needed are missing, PIE will ask if you would
like to automatically install them first (as of PIE 1.4.0).

On Windows, you do not need any build toolchain installed, since PHP extensions
for Windows are distributed as pre-compiled packages containing the extension
DLL.

## Installing PIE

- Download `pie.phar` either:
  - [latest stable release](https://github.com/php/pie/releases)
  - [latest unstable nightly](https://php.github.io/pie/pie-nightly.phar)
- Verify the PHAR's source with `gh attestation verify --owner php pie.phar`
- You may then invoke PIE with `php pie.phar <command>`

Further installation details can be found in the [usage](./docs/usage.md) docs.
This documentation assumes you have moved `pie.phar` into your `$PATH`, e.g.
`/usr/local/bin/pie` on non-Windows systems or created an alias in your shell RC file.

### One-liner

This will install PIE into `/usr/local/bin/pie` on a non-Windows system:

```shell
curl -fL --output /tmp/pie.phar https://github.com/php/pie/releases/latest/download/pie.phar \
  && gh attestation verify --owner php /tmp/pie.phar \
  && sudo mv /tmp/pie.phar /usr/local/bin/pie \
  && sudo chmod +x /usr/local/bin/pie
```

## Using PIE

### Installing a single extension using PIE

You can install an extension using the `install` command. For example, to
install the `example_pie_extension` extension, you would run:

```shell
$ pie install asgrim/example-pie-extension
🥧 PHP Installer for Extensions (PIE) 1.4.0, from The PHP Foundation
This command may need elevated privileges, and may prompt you for your password.
You are running PHP 8.5.3
Target PHP installation: 8.5.3 nts, on Linux/OSX/etc x86_64 (from /usr/bin/php8.5)
Found package: asgrim/example-pie-extension:2.0.9 which provides ext-example_pie_extension
Extracted asgrim/example-pie-extension:2.0.9 source to: /path/to/example-pie-extension
phpize complete.
Configure complete with options: --with-php-config=/usr/bin/php-config8.5
Build complete: /path/to/example-pie-extension/modules/example_pie_extension.so
Install complete: /usr/lib/php/20250925/example_pie_extension.so
✅ Extension is enabled and loaded in /usr/bin/php8.5
$
```

### Installing all extensions for a PHP project

When in your PHP project, you can install any missing top-level extensions:

```
$ pie install
🥧 PHP Installer for Extensions (PIE) 1.4.0, from The PHP Foundation
You are running PHP 8.5.0
Target PHP installation: 8.5.0 nts, on Linux/OSX/etc x86_64 (from /usr/local/bin/php)
Checking extensions for your project asgrim/demo-php-project (path: /demos/demo-php-project)
requires: ext-curl:* ✅ Already installed
requires: ext-example_pie_extension:^2.0 🚫 Missing

The following packages may be suitable, which would you like to install: 
  [0] None
  [1] asgrim/example-pie-extension: Example PIE extension
 > 1
  example_pie_extension> You are running PHP 8.5.0
  example_pie_extension> Target PHP installation: 8.5.0 nts, on Linux/OSX/etc x86_64 (from /usr/local/bin/php)
  example_pie_extension> Found package: asgrim/example-pie-extension:2.0.9 which provides ext-example_pie_extension
  example_pie_extension> Extracted asgrim/example-pie-extension:2.0.9 source to: /path/to/example-pie-extension
  example_pie_extension> phpize complete.
  example_pie_extension> Configure complete with options: --with-php-config=/usr/local/bin/php-config
  example_pie_extension> Build complete: /path/to/example-pie-extension/modules/example_pie_extension.so
  example_pie_extension> Install complete: /usr/local/lib/php/extensions/no-debug-non-zts-20250925/example_pie_extension.so
  example_pie_extension> ✅ Extension is enabled and loaded in /usr/local/bin/php

Finished checking extensions.
```

> [!TIP]
> If you are running PIE in a non-interactive shell (for example, CI, a
> container), pass the `--allow-non-interactive-project-install` flag to run
> this command. It may still fail if more than one PIE package provides a
> particular extension.

## Extensions that support PIE

A list of extensions that support PIE can be found on
[https://packagist.org/extensions](https://packagist.org/extensions).

# I have a bug, feature idea, question, need help, etc.

 - If you have an idea, question, or need help, please use the [Discussions](https://github.com/php/pie/discussions).
   - **Please check with us first before contributing new features** - it may be
     something we're already working on, as the PHP Foundation is actively
     developing this too, and there are new features already in the pipeline...
 - If you have a bug to report, please use the [Issues](https://github.com/php/pie/issues).

# I'm an extension maintainer - how do I add PIE support?

If you are an extension maintainer wanting to add PIE support to your extension,
please read [extension-maintainers](./docs/extension-maintainers.md).

# More documentation...

The full documentation for PIE can be found in [usage](./docs/usage.md) docs.
