---
title: Using PIE
order: 2
---
# PIE Usage

## Installing PIE

### Manual installation

- Download `pie.phar` from the [latest releases](https://github.com/php/pie/releases)
- Verify the PHAR's source with `gh attestation verify --owner php pie.phar`
    - Note that this step requires the [`gh` CLI command](https://github.com/cli/cli/).
- You may then invoke PIE with `php pie.phar <command>`
- Optionally, copy `pie.phar` into your `$PATH`, e.g. `cp pie.phar /usr/local/bin/pie`
    - If you copy PIE into your `$PATH`, you may then invoke PIE with `pie <command>`

This documentation assumes you have moved `pie.phar` into your `$PATH`, e.g.
`/usr/local/bin/pie` on non-Windows systems.

### Distribution packages

> [!WARNING]
> Distribution packages are not maintained by PIE, so may not have the latest version, may have patches applied, or
> the instructions here may be out of date. You should verify the distribution packages before using them.

#### Fedora and Enterprise Linux 10

On Enterprise Linux (CentOS, RHEL, AlmaLinux, RockyLinux, and other clones) you
need to enable the [EPEL](https://docs.fedoraproject.org/en-US/epel/) repository.

```shell
sudo dnf install pie
```

Package information: [pie](https://src.fedoraproject.org/rpms/pie)

#### Homebrew

PIE can be installed with Homebrew with:

```shell
brew install pie

# If you have `gh`, you can verify PIE is authentic:
gh attestation verify --owner=php $(which pie)
```

### Docker installation

PIE is published as binary-only Docker image, so you can use it easily during your Docker build:

```Dockerfile
RUN --mount=type=bind,from=ghcr.io/php/pie:bin,source=/pie,target=/usr/local/bin/pie \
    pie -V
```

Instead of `bin` tag (which represents latest binary-only image) you can also use explicit version (in `x.y.z-bin` format). Use [GitHub registry](https://ghcr.io/php/pie) to find available tags.

> [!IMPORTANT]
> Binary-only images don't include PHP runtime so you can't use them for _running_ PIE. This is just an alternative way of distributing PHAR file, you still need to satisfy PIE's runtime requirements on your own.

#### Example of PIE working in a Dockerfile

This is an example of how PIE could be used to install an extension inside a
Docker image. Note that, like Composer, you need `unzip` or the
[Zip](https://www.php.net/manual/en/book.zip.php) extension to be installed, so
that the downloaded package can be extracted.

> [!NOTE]
> Having `git` installed used to be an alternative, because Composer fell back
> to a source checkout when extraction failed. Composer 2.10 removed that
> fallback for security reasons
> ([composer/composer#12885](https://github.com/composer/composer/pull/12885)),
> and PIE uses Composer 2.10 as of 1.4.8, so `git` on its own is no longer
> enough. It is still needed for repositories that Composer reads over git,
> such as those added with `pie repository:add vcs ...`.

```Dockerfile
FROM php:8.4-cli

RUN --mount=type=bind,from=ghcr.io/php/pie:bin,source=/pie,target=/usr/local/bin/pie \
    export DEBIAN_FRONTEND="noninteractive"; \
    set -eux; \
    # Add the `unzip` package which PIE uses to extract .zip files.
    apt-get update; \
    apt-get install -y --no-install-recommends unzip; \
    # Use PIE to install an extension...
    pie install --no-cache \
        asgrim/example-pie-extension; \
    # Clean up `unzip`.
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false unzip; \
    rm -rf /var/lib/apt/lists/*;

CMD ["php", "-r", "example_pie_extension_test();"]
```

If the extension you would like to install needs additional libraries or other
dependencies, then these must be installed beforehand too.

### Executable PIE (Experimental)

As of 1.4.0 an **experimental** executable (binary) version of PIE is included.
The PIE is built using [Static PHP](https://static-php.dev/), which builds a
self-contained PHP executable with the extensions that PIE needs to run, and
bundles the PHAR as a single distributable executable. Please keep in mind that
this is **experimental**, and we do not recommend this for production use for
the time being. Please also note there are some limitations:

 - [php/pie#460](https://github.com/php/pie/discussions/460) - all the binary
   versions have the `pie self-update` feature disabled for now.

If you find the binary releases useful, please leave feedback or upvote on the
relevant discussions, so we can gauge interest in improving this functionality.

The stable versions of the executables can be found by navigating to the
[relevant release](https://github.com/php/pie/releases), and finding the
appropriate executable for your platform. For your convenience, the "latest"
stable releases can be downloaded from these links:

| Operating System | Architecture     | Download URL                                                            |
|------------------|------------------|-------------------------------------------------------------------------|
| Linux            | amd64 / x86_64   | https://github.com/php/pie/releases/latest/download/pie-Linux-X64       |
| OS X             | ARM 64 / aarch64 | https://github.com/php/pie/releases/latest/download/pie-macOS-ARM64     |
| Windows          | x86_64           | https://github.com/php/pie/releases/latest/download/pie-Windows-X64.exe |
| Linux            | ARM 64 / aarch64 | https://github.com/php/pie/releases/latest/download/pie-Linux-ARM64     |

The "nightly" versions of these can be found here:

| Operating System | Architecture     | Download URL                                  |
|------------------|------------------|-----------------------------------------------|
| Linux            | amd64 / x86_64   | https://php.github.io/pie/pie-Linux-X64       |
| OS X             | ARM 64 / aarch64 | https://php.github.io/pie/pie-macOS-ARM64     |
| Windows          | x86_64           | https://php.github.io/pie/pie-Windows-X64.exe |
| Linux            | ARM 64 / aarch64 | https://php.github.io/pie/pie-Linux-ARM64     |

We *highly* recommend you verify the file came from the PHP GitHub repository
before running it, for example:

```shell
$ gh attestation verify --owner php pie-Linux-X64
$ chmod +x pie-Linux-X64
$ ./pie-Linux-X64 --version
```

## Prerequisites for PIE (PHAR distribution)

Running PIE requires PHP 8.1 or newer. However, you may still use PIE to install
an extension for an older version of PHP. You also need the `zip` extension
enabled for the PHP version running PIE, or `git` to download the extension
source code.

Additionally to PHP, PIE requires the following build tools to be available on
your system in order to download, build and install extensions. Note that as of
PIE 1.4.0, PIE will attempt to detect and install the missing build tools:

- `autoconf`, `automake`, `libtool`, `m4`, `make`, and `gcc` to build the extension
- PHP development tools (such as `php-config` and `phpize`) to prepare the
  extension for building.

Also, each extension may have its own requirements, such as additional
libraries. As of PIE 1.4.0, for some extensions, PIE will attempt to detect and
install the missing system libraries.

> [!TIP]
> If you run PIE without the correct prerequisites installed, you may receive
> an error from the *Box Requirements Checker*. If you want to try running
> anyway, specify the environment variable `BOX_REQUIREMENT_CHECKER=0`.
>
> Example on Linux:
> ```shell
> $ BOX_REQUIREMENT_CHECKER=0 pie install foo/bar
> ```

### Using Linux

On a Debian-based system, you may install the required tools with:

```shell
sudo apt-get install git autoconf automake libtool m4 make gcc
```

On a Red Hat-based system, you may install the required tools with:

```shell
sudo yum install git autoconf automake libtool m4 make gcc
```

### Using macOS

On macOS, you may install the required tools with [Homebrew](https://brew.sh):

```shell
brew install git autoconf automake libtool m4 make gcc
```

### Using Windows

On Windows, extensions are typically distributed as precompiled binaries.
Instead of building the extension yourself, it will be downloaded as DLL
files and placed in the PHP extensions directory.

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
> C:\php-8.1.7\php.exe C:\pie.phar install --with-php-path=C:\php-8.3.6\php.exe example/example-pie-extension
```

You may also need to use the corresponding `phpize` command for the target PHP
version, which can be specified with the `--with-phpize-path` option:

```shell
pie install --with-phpize-path=/usr/bin/phpize7.2 my/extension
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

When a `version-constraint` is given, it is checked, and added directly to the
`pie.json` for the target PHP version, e.g.:

```shell
$ pie install "xdebug/xdebug:^3.4.3 || 3.4.1"
```

will set the following in `pie.json`:

```json
{
    "require": {
        "xdebug/xdebug": "^3.4.3 || 3.4.1"
    }
}
```

If no `version-constraint` is given, try to install any compatible latest and
stable version. PIE will always prefer stable versions.

### Specifying configure options

When compiling extensions, some will need additional parameters passed to the
`./configure` command. These would typically be to enable or disable certain
functionality, or to provide paths to libraries not automatically detected.

In order to determine what configure options are available for an extension,
you may use `pie info <vendor>/<package>` which will return a list, such as:

```text
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

> [!TIP]
> If you specify configure options for a package that uses the
> `pre-packaged-binary` download method, PIE will fall back to compiling the
> extension using the configure options you have specified.

### Build tools check

PIE will attempt to check the presence of build tools (such as gcc, make, etc.)
before running. If any are missing, an interactive prompt will ask if you would
like to install the missing tools. If you are running in non-interactive mode
(for example, in a CI pipeline, container build, etc), PIE will **not**
install these tools automatically. If you would like to install the build tools
in a non-interactive terminal, pass the `--auto-install-build-tools` and the
prompt will be skipped.

To skip the build tools check entirely, pass the `--no-build-tools-check` flag.

### System library dependencies check

PIE will attempt to check the presence of system library dependencies before
installing an extension. If any are missing, an interactive prompt will ask if
you would like to install the missing tools. If you are running in
non-interactive mode (for example, in a CI pipeline, container build, etc), PIE
will **not** install these dependencies automatically. If you would like to
install the system dependencies in a non-interactive terminal, pass the
`--auto-install-system-dependencies` and the prompt will be skipped.

To skip the dependencies check entirely, pass the
`--no-system-dependencies-check` flag.

### Configuring the INI file

PIE will automatically try to enable the extension by adding `extension=...` or
`zend_extension=...` in the appropriate INI file. If you want to disable this
behaviour, pass the `--skip-enable-extension` flag to your `pie install`
command. The following techniques are used to attempt to enable the extension:

 * `phpenmod`, if using the deb.sury.org distribution
 * `docker-php-ext-enable` if using Docker's PHP image
 * Add a new file to the "additional .ini file" path, if configured
 * Append to the standard php.ini, if configured

If none of these techniques work, or you used the `--skip-enable-extension`
flag, PIE will warn you that the extension was not enabled, and will note that
you must enable the extension yourself.

### Adding non-Packagist.org repositories

Sometimes you may want to install an extension from a package repository other
than Packagist.org (such as [Private Packagist](https://packagist.com/)), or
from a local directory. Since PIE is based heavily on Composer, it is possible
to use some other repository types:

* `pie repository:add [--with-php-config=...] path /path/to/your/local/extension`
* `pie repository:add [--with-php-config=...] vcs https://github.com/youruser/yourextension`
* `pie repository:add [--with-php-config=...] composer https://repo.packagist.com/your-private-packagist/`
* `pie repository:add [--with-php-config=...] composer packagist.org`

The `repository:*` commands all support the optional `--with-php-config` flag
to allow you to specify which PHP installation to use (for example, if you have
multiple PHP installations on one machine). The above added repositories can be
removed too, using the inverse `repository:remove` commands:

* `pie repository:remove [--with-php-config=...] /path/to/your/local/extension`
* `pie repository:remove [--with-php-config=...] https://github.com/youruser/yourextension`
* `pie repository:remove [--with-php-config=...] https://repo.packagist.com/your-private-packagist/`
* `pie repository:remove [--with-php-config=...] packagist.org`

Note you do not need to specify the repository type in `repository:remove`,
just the URL.

You can list the repositories for the target PHP installation with:

* `pie repository:list [--with-php-config=...]`

## Check and install missing extensions for your project

You can use `pie install` when in a PHP project working directory to check the
extensions the project requires are present. If an extension is missing, PIE
will try to find an installation candidate and interactively ask if you would
like to install one. For example:

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

## Comparison with PECL

Since PIE is a replacement for PECL, here is a comparison of the commands that
you may be familiar with in PECL, with an approximate equivalent in PIE. Note
that some concepts are different or omitted from PIE as they may simply be not
applicable to the new tooling.

| PECL                           | PIE                                                                                                                     |
|--------------------------------|-------------------------------------------------------------------------------------------------------------------------|
| `pecl build xdebug`            | `pie build xdebug/xdebug`                                                                                               |
| `pecl bundle xdebug`           | `pie download xdebug/xdebug`                                                                                            |
| `pecl channel-add channel.xml` | `pie repository:add vcs https://github.com/my/extension`                                                                |
| `pecl channel-alias`           |                                                                                                                         |
| `pecl channel-delete channel`  | `pie repository:remove https://github.com/my/extension`                                                                 |
| `pecl channel-discover`        |                                                                                                                         |
| `pecl channel-login`           |                                                                                                                         |
| `pecl channel-logout`          |                                                                                                                         |
| `pecl channel-update`          |                                                                                                                         |
| `pecl clear-cache`             |                                                                                                                         |
| `pecl config-create`           |                                                                                                                         |
| `pecl config-get`              |                                                                                                                         |
| `pecl config-help`             |                                                                                                                         |
| `pecl config-set`              |                                                                                                                         |
| `pecl config-show`             |                                                                                                                         |
| `pecl convert`                 |                                                                                                                         |
| `pecl cvsdiff`                 |                                                                                                                         |
| `pecl cvstag`                  |                                                                                                                         |
| `pecl download xdebug`         | `pie download xdebug/xdebug`                                                                                            |
| `pecl download-all`            |                                                                                                                         |
| `pecl info xdebug`             | `pie info xdebug/xdebug`                                                                                                |
| `pecl install xdebug`          | `pie install xdebug/xdebug`                                                                                             |
| `pecl list`                    | `pie show`                                                                                                              |
| `pecl list-all`                | Visit [Packagist Extension list](https://packagist.org/extensions)                                                      |
| `pecl list-channels`           | `pie repository:list`                                                                                                   |
| `pecl list-files`              |                                                                                                                         |
| `pecl list-upgrades`           |                                                                                                                         |
| `pecl login`                   |                                                                                                                         |
| `pecl logout`                  |                                                                                                                         |
| `pecl makerpm`                 |                                                                                                                         |
| `pecl package`                 | Linux - just tag a release. Windows - use [`php/php-windows-builder` action](https://github.com/php/php-windows-builder) |
| `pecl package-dependencies`    |                                                                                                                         |
| `pecl package-validate`        | In your extension checkout: `composer validate`                                                                         |
| `pecl pickle`                  |                                                                                                                         |
| `pecl remote-info xdebug`      | `pie info xdebug/xdebug`                                                                                                |
| `pecl remote-list`             | Visit [Packagist Extension list](https://packagist.org/extensions)                                                      |
| `pecl run-scripts`             |                                                                                                                         |
| `pecl run-tests`               |                                                                                                                         |
| `pecl search`                  | Visit [Packagist Extension list](https://packagist.org/extensions)                                                      |
| `pecl shell-test`              |                                                                                                                         |
| `pecl sign`                    |                                                                                                                         |
| `pecl svntag`                  |                                                                                                                         |
| `pecl uninstall`               |                                                                                                                         |
| `pecl update-channels`         |                                                                                                                         |
| `pecl upgrade xdebug`          | `pie install xdebug/xdebug`                                                                                             |
| `pecl upgrade-all`             |                                                                                                                         |
