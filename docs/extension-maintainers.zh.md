---
title: 扩展维护者
order: 3
---
# PIE 扩展维护者指南

## PIE 构建和安装步骤

### 非 Windows（例如 Linux、OSX 等）

PIE 遵循通常的 [PHP 扩展构建和安装过程](https://www.php.net/manual/zh/install.pecl.phpize.php)，即：

 * `phpize` 用于设置 PHP API 参数。如果 `phpize` 不在路径中，安装扩展的人可以指定 `--with-phpize-path`。
 * `./configure` 用于为特定系统配置构建参数和库。安装扩展的人可以指定您在 `composer.json` 中指定的配置选项。有关如何执行此操作，请参阅[配置选项](#configure-options)文档。
 * `make` 实际构建扩展。这将尝试自动检测要运行的并行进程数，但安装的人可以使用 `--make-parallel-jobs N` 或 `-jN` 选项覆盖此设置。
 * `make install` 将扩展安装到配置的 PHP 安装中。如果 PIE 没有写入安装位置的权限，它将尝试使用 `sudo` 提升权限。

请注意，这意味着运行 PIE 的系统需要安装适当的构建工具。有关构建扩展和 PHP 内部工作原理的有用资源是 [PHP Internals Book](https://www.phpinternalsbook.com/)。

### Windows

对于 Windows 系统，扩展维护者必须提供预构建的二进制文件。有关如何以正确的方式为 PIE 执行此操作的详细信息，请参阅下面的 [Windows 支持](#windows-support)部分。

## 如何为您的扩展添加 PIE 支持

为您的扩展添加 PIE 支持相对简单，流程与将常规 PHP 包添加到 Packagist 非常相似。

### 已在 PECL 上的扩展

如果您是现有 PECL 扩展的维护者，以下是一些有用的上下文信息：

 - 对于已在 PECL 中的扩展，如果您不再想发布到 PECL，则不再需要 `package.xml`。如果您现在想继续发布到 PECL，则可以继续维护 `package.xml`。
 - `package.xml` 明确列出每个版本。使用 PIE，这不再需要，因为 Packagist 会像常规 Composer 包一样获取标签或分支别名。这意味着要发布您的包，您需要推送标签和发布版本。
 - 在默认设置中，包的内容由标签或发布版本的 [Git archive](https://git-scm.com/docs/git-archive) 确定。您可以使用 [export-ignore](https://git-scm.com/docs/git-archive#Documentation/git-archive.txt-export-ignore) 属性从存档中排除文件和路径。

### 向扩展添加 `composer.json`

添加 PIE 支持的第一步是向扩展仓库添加 `composer.json`。大多数典型字段与常规 Composer 包相同，但有几个值得注意的例外：

 * `type` 必须是 `php-ext`（用于 PHP 模块，这将是大多数扩展）或 `php-ext-zend`（用于 Zend 扩展）。
 * 可以存在额外的 `php-ext` 部分（请参阅下面有关 `php-ext` 中可以包含的指令）
 * Composer 包名称（即顶级 `name` 字段）必须遵循通常的 Composer 包名称格式，即 `<vendor>/<package>`。
 * 但是，请注意，PIE 扩展的 Composer 包名称不得与常规 PHP 包共享相同的 Composer 包名称，即使它们具有不同的 `type` 字段。

#### `php-ext` 定义

##### `extension-name`

可以指定 `extension-name`，并且必须符合通常的扩展名称正则表达式，该表达式在 [\Php\Pie\ExtensionName::VALID_PACKAGE_NAME_REGEX](../src/ExtensionName.php) 中定义。如果未指定 `extension-name`，则 `extension-name` 将从 Composer 包名称派生，删除供应商前缀。例如，给定一个 `composer.json`：

```json
{
    "name": "myvendor/myextension"
}
```

扩展名称将派生为 `myextension`。`myvendor/` 供应商前缀被删除。

> [!WARNING]
> 如果您的 Composer 包名称会导致无效的 PHP 扩展名称，您必须指定 `extension-name` 指令。例如，Composer 包名称 `myvendor/my-extension` 会导致无效的 PHP 扩展名称，因为不允许使用连字符，因此您必须为此 Composer 包名称指定有效的 `extension-name`。

`extension-name` 不应以 `ext-` 为前缀，这是 Composer 在使用 `require` 时的约定。

使用 `extension-name` 的示例：

```json
{
    "name": "xdebug/xdebug",
    "php-ext": {
        "extension-name": "xdebug"
    }
}
```

##### `priority`

`priority` 构成 `ini` 文件名的一部分，用于控制扩展的顺序，如果目标平台使用目录中的多个 INI 文件。

##### `support-zts` 和 `support-nts`

指示扩展是否支持 Zend Thread-Safe（ZTS）和非线程安全（NTS）模式。如果未指定，这两个标志都默认为 `true`，但如果您的扩展不支持任一模式，则必须指定，这将意味着扩展无法在目标平台上安装。

理论上，可以为 `support-zts` 和 `support-nts` 都指定 `false`，但这将意味着您的包无法在任何地方安装，因此不建议这样做。

##### `configure-options`

这是可以传递给 `./configure` 命令的参数列表。列表的每个项目都是一个 JSON 对象，包含：

 * `name`，参数名称本身
 * `description`，参数作用的有用描述
 * 可选的 `needs-value`，一个布尔值，告诉 PIE 参数是简单标志（通常用于 `--enable-this-flag` 类型参数），还是参数应该有一个指定的值（通常用于 `--with-library-path=...` 类型参数，其中最终用户必须提供一个值）

当最终用户使用 PIE 安装扩展时，他们可以指定传递给 `./configure` 的任何已定义的 `configure-options`。例如，如果扩展定义了以下 `composer.json`：

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

然后可以以以下方式调用 `pie build` 或 `pie install` 命令以实现所需的配置：

 * `pie install myvendor/myext`
   * 这将简单地调用 `./configure` 而不带任何参数
 * `pie install myvendor/myext --enable-my-flag`
   * 这将调用 `./configure --enable-my-flag`
 * `pie install myvendor/myext --with-some-lib=/path/to/somelib`
   * 这将调用 `./configure --with-some-lib=/path/to/somelib`
 * `pie install myvendor/myext --enable-my-flag --with-some-lib=/path/to/somelib`
   * 这将调用 `./configure --enable-my-flag --with-some-lib=/path/to/somelib`

请注意，PIE 的最终用户无法指定未在您的扩展的 `configure-options` 定义中定义的配置选项。使用上面相同的 `composer.json` 示例，使用无效选项调用 PIE，例如 `pie install myvendor/myext --something-else` 将导致错误 `The "--something-else" option does not exist.`。

如果最终用户未指定 `configure-options` 定义中定义的标志，则不会将其传递给 `./configure`。无法在 `configure-options` 定义中指定默认值。您的 `config.m4` 应相应地处理此问题。

##### `build-path`

如果源代码不在仓库的根目录中，可以使用 `build-path` 设置。例如，如果您的仓库结构如下：

```text
/
  docs/
  src/
    config.m4
    config.w32
    myext.c
    ...etc
```

在这种情况下，实际的扩展源代码将在 `src/` 中构建，因此您应该在 `build-path` 中指定此路径，例如：

```json
{
    "name": "myvendor/myext",
    "php-ext": {
        "extension-name": "myext",
        "build-path": "src"
    }
}
```

`build-path` 可以包含一些被替换的模板值：

 * `{version}` 将被替换为包版本。例如，版本为 1.2.3 的包，`build-path` 为 `myext-{version}`，实际构建路径将变为 `myext-1.2.3`。

##### `download-url-method`

`download-url-method` 指令允许扩展维护者更改下载源包的行为。

 * 将其设置为 `composer-default`（如果未指定则为默认值），将使用 Composer 实现的默认行为，即使用来自 GitHub API（或其他源代码控制系统）的标准 ZIP 存档。
 * 使用 `pre-packaged-source` 将在发布资源列表中定位源代码包，该包基于以下命名约定之一：
   * `php_{ExtensionName}-{Version}-src.tgz`（例如 `php_myext-1.20.1-src.tgz`）
   * `php_{ExtensionName}-{Version}-src.zip`（例如 `php_myext-1.20.1-src.zip`）
   * `{ExtensionName}-{Version}.tgz`（这是为了与 PECL 包向后兼容）

##### `os-families` 限制

`os-families` 和 `os-families-exclude` 指令允许扩展维护者限制操作系统兼容性。

 * `os-families` 一个操作系统家族的数组，用于标记与扩展兼容。（例如 `"os-families": ["windows"]` 表示仅在 Windows 上可用的扩展）
 * `os-families-exclude` 一个操作系统家族的数组，用于标记与扩展不兼容。（例如 `"os-families-exclude": ["windows"]` 表示无法在 Windows 上安装的扩展）

接受的操作系统家族列表："windows"、"bsd"、"darwin"、"solaris"、"linux"、"unknown"

> [!WARNING]
> 只能定义 `os-families` 和 `os-families-exclude` 之一。

#### 扩展依赖项

扩展作者可以在 `require` 中定义一些依赖项，但实际上，大多数扩展不需要定义依赖项，除了扩展支持的 PHP 版本。可以定义对其他扩展的依赖项，例如 `ext-json`。但是，不应在 `require` 部分指定对常规 PHP 包（如 `monolog/monolog`）的依赖项。

值得注意的是，如果您的扩展确实定义了对另一个依赖项的依赖，并且此依赖项不可用，则安装您的扩展的人将收到如下消息：

```
Cannot use myvendor/myextension's latest version 1.2.3 as it requires
ext-something * which is missing from your platform.
```

#### 检查扩展是否能工作

首先，您可以使用 `composer validate` 检查您的 `composer.json` 格式是否正确，例如：

```shell
$ composer validate
./composer.json is valid
```

然后，您可以在扩展目录中使用 `pie install` 安装扩展：

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

##### 仅构建不安装

如果您只想测试应用程序的构建而不将其安装到目标 PHP 版本，您首先需要将扩展目录添加为"path"类型仓库：

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

然后，您可以测试它是否构建：

```shell
$ pie build asgrim/example-pie-extension:*@dev
```

> [!TIP]
> 由于您的扩展尚未发布到 Packagist，因此应指定 `*@dev` 作为版本约束，否则 PIE 将找不到您的扩展，因为默认稳定性为 `stable`。

### 将扩展提交到 Packagist

将 `composer.json` 提交到仓库后，您可以像提交任何其他包一样将其提交到 Packagist。

 * 前往 [https://packagist.org/packages/submit](https://packagist.org/packages/submit)
 * 输入您的仓库的 URL 并按照说明操作。

### Windows 支持

为了支持 Windows 用户，您必须发布预构建的 DLL，因为 PIE 目前不支持即时构建 DLL。Windows 兼容版本的预期工作流程是：

 - 在 GitHub 上进行发布（目前仅支持 GitHub）
 - CI 流水线运行以构建发布资源，例如在 GitHub Action 中
 - 生成的构建资源以 ZIP 文件形式发布到 GitHub 发布版本

ZIP 文件的名称以及其中包含的 DLL 必须是：

* `php_{extension-name}-{tag}-{php-maj/min}-{ts|nts}-{compiler}-{arch}.zip`
* 示例：`php_xdebug-3.3.2-8.3-ts-vs16-x86_64.zip`

这些项目的描述：

* `extension-name` 扩展名称，例如 `xdebug`
* `tag` 例如 `3.3.0alpha3` - 由您制作的标签/发布版本定义
* `php-maj/min` - 例如 `8.3` 表示 PHP 8.3.*
* `compiler` - 通常是像 `vc6`、`vs16` 这样的东西 - 从 `php -i` 中的 'PHP Extension Build' 标志获取
* `ts|nts` - 线程安全或非线程安全。
* `arch` - 例如 `x86_64`。
   * Windows：使用 `php -i` 中 `Architecture` 的提示（见下文）
   * 非 Windows：检查 `PHP_INT_SIZE` - 32 位为 4，64 位为 8。

请注意，架构名称可能需要规范化，因为不同平台对架构的命名不同。PIE 期望以下规范化的架构：

 * `x86_64`（从 `x64`、`x86_64`、`AMD64` 规范化）
 * `arm64`（从 `arm64` 规范化）
 * `x86`（任何其他值）

有关最新映射（如果文档不是最新的），请查看 `\Php\Pie\Platform\Architecture::parseArchitecture`。

#### Windows ZIP 的内容

预构建的 ZIP 应至少包含一个与 ZIP 本身命名相同的 DLL，例如 `php_{extension-name}-{tag}-{php-maj/min}-{ts|nts}-{compiler}-{arch}.dll`。`.dll` 将被移动到 PHP 扩展路径并重命名，例如移动到 `C:\path\to\php\ext\php_{extension-name}.dll`。ZIP 文件可能包含额外的资源，例如：

* `php_{extension-name}-{tag}-{php-maj/min}-{ts|nts}-{compiler}-{arch}.pdb` - 这将被移动到 `C:\path\to\php\ext\php_{extension-name}.dll` 旁边
* `*.dll` - 任何其他 `.dll` 都将被移动到 `C:\path\to\php\php.exe` 旁边
* 任何其他文件，将被移动到 `C:\path\to\php\extras\{extension-name}\.`

#### Windows 发布的自动化

PHP 提供了一组 [GitHub Actions](https://github.com/php/php-windows-builder)，使扩展维护者能够构建和发布 Windows 兼容资源。使用这些操作的示例工作流程：

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

来源：[https://github.com/php/php-windows-builder?tab=readme-ov-file#example-workflow-to-build-and-release-an-extension](https://github.com/php/php-windows-builder?tab=readme-ov-file#example-workflow-to-build-and-release-an-extension)

