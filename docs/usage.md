# PIE Usage for Extension Maintainers

## Installing PIE

### Manual installation

- Download `pie.phar` from the [latest releases](https://github.com/php/pie/releases)
- Validate the signature in `pie.phar.asc`
- You may then invoke PIE with `php pie.phar <command>`
- Optionally, copy `pie.phar` into your `$PATH`, e.g. `cp pie.phar /usr/local/bin/pie`
    - If you copy PIE into your `$PATH`, you may then invoke PIE with `pie <command>`

This documentation assumes you have moved `pie.phar` into your `$PATH`, e.g.
`/usr/local/bin/pie` on non-Windows systems.

### One-liner

Note that this does not verify any signatures, and you assume the risks in
running this, but this will put PIE into `/usr/local/bin/pie` on a non-Windows
system:

```shell
sudo curl -L --output /usr/local/bin/pie https://github.com/php/pie/releases/latest/download/pie.phar && sudo chmod +x /usr/local/bin/pie
```

## Prerequisities for PIE

Running PIE requires PHP 8.1 or newer. However, you may still use PIE to install
an extension for an older version of PHP.

## Downloading, Building, or Installing an extension

PIE has the ability to:

 - only download an extension, with `pie download ...`,
 - download and build an extension, with `pie build ...`,
 - or, most commonly, download, build, and install an extension, with `pie install ...`

When installing an extension with PIE, you must use its Composer package name.
You can find a list of PIE-compatible packages on
[https://packagist.org/extensions](https://packagist.org/extensions).

Once you know the extension name, you can install it with:

```shell
pie install <vendor>/<package>

# for example:
pie install xdebug/xdebug
```

This will install the Xdebug extension into the version of PHP that is used to
invoke PIE, using whichever is the latest stable version of Xdebug compatible
with that version of PHP.

### Using PIE to install an extension for a different PHP version

If you are trying to install an extension for a different version of PHP, you
may specify this on non-Windows systems with the `--with-php-config` option:

```shell
pie install --with-php-config=/usr/bin/php-config7.2 my/extension
```

On Windows, you may provide a path to the `php` executable itself using the
`--with-php-path` option. This is an example on Windows where PHP 8.1 is used
to run PIE, but we want to download the extension for PHP 8.3:

```shell
> C:\php-8.3.6\php.exe C:\pie.phar install --with-php-path=C:\php-8.1.7\php.exe example/example-pie-extension
```

### Version constraints and stability

You may optionally specify a version constraint when using PIE to install an
extension:

```bash
pie install <vendor>/<package>:<version-constraint>
```

If `version-constraint` is given, try to install that version if it matches the
allowed versions. Version constraints are resolved using the same format as
Composer, along with the minimum stability.

* `^1.0` will install the latest stable and backwards-compatible version with
  `1.0.0` and above, according to semantic versioning.
  [See Composer docs for details](https://getcomposer.org/doc/articles/versions.md#caret-version-range-).
* `^2.3@beta` will install the latest beta and backwards-compatible version
  with `2.3.0` and above (for example, `2.3.0-beta.3`).
* `dev-main` will install the latest commit on the `main` branch at the time
  of command execution. This would not work with Windows, as there is no
  release with Windows binaries.
* `dev-main#07f454ad797c30651be8356466685b15331f72ff` will install the specific
  commit denoted by the commit sha after `#`, in this case the commit
  `07f454ad797c30651be8356466685b15331f72ff` would be installed. This would
  not work with Windows, as there is no release with Windows binaries.

If no `version-constraint` is given, try to install any compatible latest and
stable version. PIE will always prefer stable versions.

### Specifying configure options

When compiling extensions, some will need additional parameters passed to the
`./configure` command. These would typically be to enable or disable certain
functionality, or to provide paths to libraries not automatically detected.

In order to determine what configure options are available for an extension,
you may use `pie info <vendor>/<package>` which will return a list, such as:

```
Configure options:
    --enable-some-functionality  (whether to enable some additional functionality provided)
    --with-some-library-name=?  (Path for some-library)
```

The above example extension could then be installed with none, some, or all of
the specified configure options, some examples:

```shell
pie install example/some-extension
pie install example/some-extension --enable-some-functionality
pie install example/some-extension --with-some-library-name=/path/to/the/lib
pie install example/some-extension --with-some-library-name=/path/to/the/lib --enable-some-functionality
```

### Configuring the INI file

At the moment, PIE does not configure the INI file, although this improvement
is planned soon. In the meantime, you must enable the extension after installing
by adding a line such as `extension=foo` to your `php.ini`.
