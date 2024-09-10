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
 * The Composer package name (i.e. the top level `name` field) for a PIE
   extension cannot share the same Composer package name as a regular PHP
   package, even if they have different `type` fields.

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

The `extension-name` MAY be prefixed with `ext-` as is a convention in Composer,
but this is optional.

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

#### `support-zts`

Indicates whether the extension supports Zend Thread-Safe (ZTS) mode or not.

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

## Submit the extension to Packagist

Once you have committed your `composer.json` to your repository, you may then
submit it to Packagist in the same way as any other package.

 * Head to [https://packagist.org/packages/submit](https://packagist.org/packages/submit)
 * Enter the URL of your repository, and follow the instructions.
