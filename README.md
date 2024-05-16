# 🥧 PIE (PHP Installer for Extensions)

You will need PHP 8.1 or newer to run PIE, but PIE can install an extension to any installed PHP version.

## Installing

## Usage

You can download an extension ready to be built or installed using the `download` command. For example, to download the
`example_pie_extension` extension, you would run:

```shell
$ bin/pie download asgrim/example-pie-extension
You are running PHP 8.3.7
Target PHP installation: 8.3.7 (from /usr/bin/php8.3)
Platform: NonWindows, x86_64, NonThreadSafe
Found package: asgrim/example-pie-extension:1.0.1 which provides ext-example_pie_extension
Extracted asgrim/example-pie-extension:1.0.1 source to: /tmp/pie_downloader_6645f07a28bec9.66045489/asgrim-example-pie-extension-769f906
$ 
```

If you are trying to install an extension for a different version of PHP, you may specify this on non-Windows systems
with the `--with-php-config` option like:

```shell
bin/pie download --with-php-config=/usr/bin/php-config7.2 my/extension
```

Windows TBD

## Developing

### Testing

### Building and Deploying
