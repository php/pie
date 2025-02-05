---
title: Extension Maintainers
order: 3
---
# PIE for Extension Maintainers

Adding PIE support for your extension is relatively straightforward, and the
flow is quite similar to adding a regular PHP package into Packagist.

## Add a `composer.json` to your extension

The first step to adding PIE support is adding a `composer.json` to your
extension repository. Most of the typical fields are the same, with a few
notable exceptions:

 * The `type` MUST be either `php-ext` for a PHP Module (this will be most
   extensions), or `php-ext-zend` for a Zend Extension.
 * An additional `php-ext` section MAY exist.
 * The Composer package name (i.e. the top level `name` field) should follow
   the usual Composer package name format, i.e. `<vendor>/<package>`.
 * However, please note that the Composer package name for a PIE extension
   cannot share the same Composer package name as a regular PHP package, even
   if they have different `type` fields.

### The `php-ext` definition

#### `extension-name`

The `extension-name` SHOULD be specified, and must conform to the usual extension
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

#### `priority`

`priority` is currently not used, but will form part of the `ini` filename to
control ordering of extensions, if the target platform uses it.

#### `support-zts` and `support-nts`

Indicates whether the extension supports Zend Thread-Safe (ZTS) and non-Thread-
Safe (NTS) modes. Both these flags default to `true` if not specified, but if
your extension does not support either mode, it should be specified, and will
mean the extension will not be installable on the target platform.

Theoretically, it is possible to specify `false` for both `support-zts` and
`support-nts`, but this will mean your package cannot be installed anywhere, so
is not advisable.

#### `configure-options`

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
definition, it will simply not be passed to `./configure`. There is no way to
specify a default value in the `configure-options` definition.

#### `build-path`

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

#### `override-download-url-method`

The `override-download-url-method` directive allows extension maintainers to
change the behaviour of downloading the source package.

 * Setting this to `composer-default`, which is the default value if not
   specified, will use the default behaviour implemented by Composer, which is
   to use the standard ZIP archive from the GitHub API (or other source control
   system).
 * Using `pre-packaged-source` will locate a source code package in the release
   assets list based matching one of the following naming conventions:
   * `php_{ExtensionName}-{Version}-src.tgz` (e.g. `php_myext-1.20.1-src.tgz`)
   * `php_{ExtensionName}-{Version}-src.zip` (e.g. `php_myext-1.20.1-src.zip`)

### Extension dependencies

Extension authors may define some dependencies in `require`, but practically,
most extensions would not need to define dependencies. Dependencies on other
extensions may be defined, for example `ext-json`. However, dependencies on
a regular PHP package (such as `monolog/monolog`) are ignored when requesting
an installation of an extension with PIE.

It is worth noting that if your extension does define a dependency on another
dependency, this would prevent installation of the extension, and at the moment
the messaging around this is
[not particularly clear](https://github.com/php/pie/issues/15).

## Submit the extension to Packagist

Once you have committed your `composer.json` to your repository, you may then
submit it to Packagist in the same way as any other package.

 * Head to [https://packagist.org/packages/submit](https://packagist.org/packages/submit)
 * Enter the URL of your repository, and follow the instructions.

## Windows Support

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
   * Windows: `Architecture` from `php -i`
   * non-Windows: check `PHP_INT_SIZE` - 4 for 32-bit, 8 for 64-bit.

### Contents of the Windows ZIP

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

### Automation of the Windows publishing

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
