---
title: Extension Maintainers
order: 3
---
# PIE for Extension Maintainers

## The PIE build & install steps

### Non-Windows (e.g. Linux, OSX, etc.)

PIE follows the usual [PHP extension build and install process](https://www.php.net/manual/en/install.pecl.phpize.php),
namely:

 * `phpize` to set up the PHP API parameters. The person installing the
   extension may specify `--with-phpize-path` if `phpize` is not in the path.
 * `./configure` to configure the build parameters and libraries for the
   specific system. The person installing the extension may specify the
   configure options that you have specified in `composer.json`. See the
   [Configure Options](#configure-options) documentation for how to do this.
 * `make` to actually build the extension. This will try to detect the number
   of parallel processes to run automatically, but the person installing may
   override this with `--make-parallel-jobs N` or `-jN` options.
 * `make install` to install the extension to the configured PHP installation.
   If PIE does not have permissions to write to the installation location, it
   will attempt to elevate privileges with `sudo`.

Note that this does mean the systems you are running PIE on need to have the
appropriate build tools installed. A useful resource for building extensions
and how PHP's internal works is the [PHP Internals Book](https://www.phpinternalsbook.com/).

### Windows

For Windows systems, extension maintainers must provide pre-built binaries. See
the [Windows Support](#windows-support) section below for details on how to
do this in the right way for PIE.

## How to add PIE support for your extension

Adding PIE support for your extension is relatively straightforward, and the
flow is quite similar to adding a regular PHP package into Packagist.

### Extensions already on PECL

If you are a maintainer of an existing PECL extension, here are a few helpful
pieces of information for some context:

 - For an extension already in PECL, the `package.xml` is no longer needed if
   you no longer want to publish to PECL. If you want to keep publishing to
   PECL for now, then you can keep `package.xml` maintained.
 - The `package.xml` lists each release explicitly. With PIE, this is no longer
   necessary, as Packagist will pick up tags or branch aliases in the same
   way that regular Composer packages do. This means that to release your
   package, you need to push a tag and release.
 - In the default setup, the contents of the package are determined by the
   [Git archive](https://git-scm.com/docs/git-archive) for the tag or revision
   of the release. You can exclude files and paths from the archive with the
   [export-ignore](https://git-scm.com/docs/git-archive#Documentation/git-archive.txt-export-ignore)
   attribute.

### Add a `composer.json` to your extension

The first step to adding PIE support is adding a `composer.json` to your
extension repository. Most of the typical fields are the same as a regular
Composer package, with a few notable exceptions:

 * The `type` MUST be either `php-ext` for a PHP Module (this will be most
   extensions), or `php-ext-zend` for a Zend Extension.
 * An additional `php-ext` section MAY exist (see below for the directives
   that can be within `php-ext`)
 * The Composer package name (i.e. the top level `name` field) MUST follow
   the usual Composer package name format, i.e. `<vendor>/<package>`.
 * However, please note that the Composer package name for a PIE extension
   MUST NOT share the same Composer package name as a regular PHP package, even
   if they have different `type` fields.

#### The `php-ext` definition

##### `extension-name`

The `extension-name` MAY be specified, and MUST conform to the usual extension
name regular expression, which is defined in
[\Php\Pie\ExtensionName::VALID_PACKAGE_NAME_REGEX](../src/ExtensionName.php).
If the `extension-name` is not specified, the `extension-name` will be derived
from the Composer package name, with the vendor prefix removed. For example,
given a `composer.json` with:

```json
{
    "name": "myvendor/myextension"
}
```

The extension name would be derived as `myextension`. The `myvendor/` vendor
prefix is removed.

> [!WARNING]
> If your Composer package name would result in an invalid PHP extension name,
> you MUST specify the `extension-name` directive. For example a Composer
> package name `myvendor/my-extension` would result in an invalid PHP extension
> name, since hypens are not allowed, so you MUST specify a valid
> `extension-name` for this Composer package name.

The `extension-name` SHOULD NOT be prefixed with `ext-` as is a convention in
Composer when using `require`.

An example of `extension-name` being used:

```json
{
    "name": "xdebug/xdebug",
    "php-ext": {
        "extension-name": "xdebug"
    }
}
```

##### `priority`

`priority` forms part of the `ini` filename to control ordering of extensions,
if the target platform uses the multiple INI files in a directory.

##### `support-zts` and `support-nts`

Indicates whether the extension supports Zend Thread-Safe (ZTS) and non-Thread-
Safe (NTS) modes. Both these flags default to `true` if not specified, but if
your extension does not support either mode, it MUST be specified, and will
mean the extension will not be installable on the target platform.

Theoretically, it is possible to specify `false` for both `support-zts` and
`support-nts`, but this will mean your package cannot be installed anywhere, so
is not advisable.

##### `configure-options`

This is a list of parameters that may be passed to the `./configure` command.
Each item of the list is a JSON object with:

 * `name`, the parameter name itself
 * `description`, a helpful description of what the parameter does
 * optionally, `needs-value`, a boolean to tell PIE whether the parameter is a
   simple flag (typically used for `--enable-this-flag` type parameters), or if
   the parameter should have a value specified (typically used for
   `--with-library-path=...` type parameters, where a value must be given by
   the end user)

When an end user is installing an extension with PIE, they may specify any
defined `configure-options` that are passed to `./configure`. For example, if
an extension defines the following `composer.json`:

```json
{
    "name": "myvendor/myext",
    "php-ext": {
        "extension-name": "myext",
        "configure-options": [
            {
                "name": "enable-my-flag",
                "description": "Should my flag be enabled",
                "needs-value": false
            },
            {
                "name": "with-some-lib",
                "description": "Specify the path to some-lib",
                "needs-value": true
            }
        ]
    }
}
```

Then the `pie build` or `pie install` commands may be invoked in the following
ways to achieve the desired configuration:

 * `pie install myvendor/myext`
   * This will simply invoke `./configure` without any parameters
 * `pie install myvendor/myext --enable-my-flag`
   * This will invoke `./configure --enable-my-flag`
 * `pie install myvendor/myext --with-some-lib=/path/to/somelib`
   * This will invoke `./configure --with-some-lib=/path/to/somelib`
 * `pie install myvendor/myext --enable-my-flag --with-some-lib=/path/to/somelib`
   * This will invoke `./configure --enable-my-flag --with-some-lib=/path/to/somelib`

Note that it is not possible for end users of PIE to specify configuration
options that have not been defined in your extension's `configure-options`
definition. Using the same example above `composer.json`, invoking PIE with
an invalid option, such as `pie install myvendor/myext --something-else` will
result in an error `The "--something-else" option does not exist.`.

If an end user does not specify a flag defined in the `configure-options`
definition, it will not be passed to `./configure`. There is no way to specify
a default value in the `configure-options` definition. Your `config.m4` should
handle this accordingly.

##### `build-path`

The `build-path` setting may be used if your source code is not in the root
of your repository. For example, if your repository structure is like:

```text
/
  docs/
  src/
    config.m4
    config.w32
    myext.c
    ...etc
```

In this case, the actual extension source code would be built in `src/`, so you
should specify this path in `build-path`, for example:

```json
{
    "name": "myvendor/myext",
    "php-ext": {
        "extension-name": "myext",
        "build-path": "src"
    }
}
```

The `build-path` may contain some templated values which are replaced:

 * `{version}` to be replaced with the package version. For example a package
   with version 1.2.3 with a `build-path` of `myext-{version}` the actual build
   path would become `myext-1.2.3`.

##### `download-url-method`

The `download-url-method` directive allows extension maintainers to
change the behaviour of downloading the source package.

 * Setting this to `composer-default`, which is the default value if not
   specified, will use the default behaviour implemented by Composer, which is
   to use the standard ZIP archive from the GitHub API (or other source control
   system).
 * Using `pre-packaged-source` will locate a source code package in the release
   assets list based matching one of the following naming conventions:
   * `php_{ExtensionName}-{Version}-src.tgz` (e.g. `php_myext-1.20.1-src.tgz`)
   * `php_{ExtensionName}-{Version}-src.zip` (e.g. `php_myext-1.20.1-src.zip`)
   * `{ExtensionName}-{Version}.tgz` (this is intended for backwards
     compatibility with PECL packages)

##### `os-families` restrictions

The `os-families` and `os-families-exclude` directive allow extention maintainers
to restrict the Operating System compatibility.

 * `os-families` An array of OS families to mark as compatible with the extension.
   (e.g. `"os-families": ["windows"]` for an extension only available on Windows)
 * `os-families-exclude` An array of OS families to mark as incompatible with the
   extension. (e.g. `"os-families-exclude": ["windows"]` for an extension cannot
   be installed available on Windows)

The list of accepted OS families: "windows", "bsd", "darwin", "solaris", "linux",
"unknown"

> Note: only once of `os-families` and `os-families-exclude` can be defined.

#### Extension dependencies

Extension authors may define some dependencies in `require`, but practically,
most extensions would not need to define dependencies, except for the PHP
versions supported by the extension. Dependencies on other extensions may be
defined, for example `ext-json`. However, dependencies on a regular PHP package
(such as `monolog/monolog`) SHOULD NOT be specified in your `require` section.

It is worth noting that if your extension does define a dependency on another
dependency, and this is not available, someone installing your extension would
receive a message such as:

```
Cannot use myvendor/myextension's latest version 1.2.3 as it requires
ext-something * which is missing from your platform.
```

#### Checking the extension will work

First up, you can use `composer validate` to check your `composer.json` is
formatted correctly, e.g.:

```shelle
$ composer validate
./composer.json is valid
```

You may then use `pie install` to install your extension while in its directory:

```shell
$ cd /path/to/my/extension
$ pie install
🥧 PHP Installer for Extensions (PIE) 1.0.0, from The PHP Foundation
Installing PIE extension from /home/james/workspace/phpf/example-pie-extension
This command may need elevated privileges, and may prompt you for your password.
You are running PHP 8.4.8
Target PHP installation: 8.4.8 nts, on Linux/OSX/etc x86_64 (from /usr/bin/php8.4)
Found package: asgrim/example-pie-extension:dev-main which provides ext-example_pie_extension
Extracted asgrim/example-pie-extension:dev-main source to: /home/james/.config/pie/php8.4_572ee73609adb95bf0b8539fecdc5c0e/vendor/asgrim/example-pie-extension
Build files cleaned up.
phpize complete.
Configure complete.
Build complete: /home/james/.config/pie/php8.4_572ee73609adb95bf0b8539fecdc5c0e/vendor/asgrim/example-pie-extension/modules/example_pie_extension.so
Cannot write to /usr/lib/php/20240924, so using sudo to elevate privileges.
Install complete: /usr/lib/php/20240924/example_pie_extension.so
✅ Extension is enabled and loaded in /usr/bin/php8.4
```

##### Building without installing

If you want to just test the build of your application, without installling it
to your target PHP version, you will first need to your extension directory as
a "path" type repository:

```shell
$ cd /path/to/my/extension
$ pie repository:add path .
🥧 PHP Installer for Extensions (PIE) 1.0.0, from The PHP Foundation
You are running PHP 8.4.8
Target PHP installation: 8.4.8 nts, on Linux/OSX/etc x86_64 (from /usr/bin/php8.4)
The following repositories are in use for this Target PHP:
  - Path Repository (/home/james/workspace/phpf/example-pie-extension)
  - Packagist
```

Then you may test that it builds with:

```shell
$ pie build asgrim/example-pie-extension:*@dev
```

> [!TIP]
> Since your extension is not yet published to Packagist, you should specify
> `*@dev` as the version constraint, otherwise PIE will not find your extension
> as the default stability is `stable`.

### Submit the extension to Packagist

Once you have committed your `composer.json` to your repository, you may then
submit it to Packagist in the same way as any other package.

 * Head to [https://packagist.org/packages/submit](https://packagist.org/packages/submit)
 * Enter the URL of your repository and follow the instructions.

### Windows Support

In order to support Windows users, you must publish pre-built DLLs, as PIE does
not currently support building DLLs on the fly. The expected workflow for
Windows-compatible releases is:

 - The release is made on GitHub (only GitHub is supported at the moment)
 - A CI pipeline runs to build the release assets, e.g. in a GitHub Action
 - The resulting build assets are published to the GitHub release in a ZIP file

The name of the ZIP file, and the DLL contained within must be:

* `php_{extension-name}-{tag}-{php-maj/min}-{ts|nts}-{compiler}-{arch}.zip`
* Example: `php_xdebug-3.3.2-8.3-ts-vs16-x86_64.zip`

The descriptions of these items:

* `extension-name` the name of the extension, e.g. `xdebug`
* `tag` for example `3.3.0alpha3` - defined by the tag/release you have made
* `php-maj/min` - for example `8.3` for PHP 8.3.*
* `compiler` - usually something like `vc6`, `vs16` - fetch from
  'PHP Extension Build' flags in `php -i`
* `ts|nts` - Thread-safe or non-thread safe.
* `arch` - for example `x86_64`.
   * Windows: use a hint from `Architecture` from `php -i` (see below)
   * non-Windows: check `PHP_INT_SIZE` - 4 for 32-bit, 8 for 64-bit.

Note the architecture name will likely need normalising, since different
platforms name architectures differently. PIE expects the following normalised
architectures:

 * `x86_64` (normalised from `x64`, `x86_64`, `AMD64`)
 * `arm64` (normalised from `arm64`)
 * `x86` (any other value)

For the latest map (in case documentation is not up to date), check out
`\Php\Pie\Platform\Architecture::parseArchitecture`.

#### Contents of the Windows ZIP

The pre-built ZIP should contain at minimum a DLL named in the same way as the
ZIP itself, for example
`php_{extension-name}-{tag}-{php-maj/min}-{ts|nts}-{compiler}-{arch}.dll`.
The `.dll` will be moved into the PHP extensions path, and renamed, e.g.
to `C:\path\to\php\ext\php_{extension-name}.dll`. The ZIP file may include
additional resources, such as:

* `php_{extension-name}-{tag}-{php-maj/min}-{ts|nts}-{compiler}-{arch}.pdb` -
  this will be moved alongside the `C:\path\to\php\ext\php_{extension-name}.dll`
* `*.dll` - any other `.dll` would be moved alongside `C:\path\to\php\php.exe`
* Any other file, which would be moved
  into `C:\path\to\php\extras\{extension-name}\.`

#### Automation of the Windows publishing

PHP provides a [set of GitHub Actions](https://github.com/php/php-windows-builder)
that enable extension maintainers to build and release the Windows compatible
assets. An example workflow that uses these actions:

```yaml
name: Publish Windows Releases
on:
   release:
      types: [published]

permissions:
   contents: write

jobs:
   get-extension-matrix:
      runs-on: ubuntu-latest
      outputs:
         matrix: ${{ steps.extension-matrix.outputs.matrix }}
      steps:
         - name: Checkout
           uses: actions/checkout@v4
         - name: Get the extension matrix
           id: extension-matrix
           uses: php/php-windows-builder/extension-matrix@v1
   build:
      needs: get-extension-matrix
      runs-on: ${{ matrix.os }}
      strategy:
         matrix: ${{fromJson(needs.get-extension-matrix.outputs.matrix)}}
      steps:
         - name: Checkout
           uses: actions/checkout@v4
         - name: Build the extension
           uses: php/php-windows-builder/extension@v1
           with:
              php-version: ${{ matrix.php-version }}
              arch: ${{ matrix.arch }}
              ts: ${{ matrix.ts }}
   release:
      runs-on: ubuntu-latest
      needs: build
      if: ${{ github.event_name == 'release' }}
      steps:
         - name: Upload artifact to the release
           uses: php/php-windows-builder/release@v1
           with:
              release: ${{ github.event.release.tag_name }}
              token: ${{ secrets.GITHUB_TOKEN }}
```

Source: [https://github.com/php/php-windows-builder?tab=readme-ov-file#example-workflow-to-build-and-release-an-extension](https://github.com/php/php-windows-builder?tab=readme-ov-file#example-workflow-to-build-and-release-an-extension)
