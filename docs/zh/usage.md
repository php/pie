---
title: 使用 PIE
order: 2
en-revision: b595732a62673b53c2509dfeab38a71ee16c88f4
---
> [!WARNING]
> This translation may not be based on the latest version, please ensure you
> check the original English version for discrepancies.

# PIE 使用指南

## 安装 PIE

### 手动安装

- 从[最新发布版本](https://github.com/php/pie/releases)下载 `pie.phar`
- 使用 `gh attestation verify --owner php pie.phar` 验证 PHAR 的来源
    - 注意此步骤需要安装 [`gh` CLI 命令](https://github.com/cli/cli/)。
- 然后可以使用 `php pie.phar <command>` 调用 PIE
- 可选：将 `pie.phar` 复制到您的 `$PATH`，例如 `cp pie.phar /usr/local/bin/pie`
    - 如果将 PIE 复制到 `$PATH`，则可以使用 `pie <command>` 调用 PIE

本文档假设您已将 `pie.phar` 移动到 `$PATH`，例如非 Windows 系统上的 `/usr/local/bin/pie`。

### 一键安装

注意这不会验证任何签名，您需要自行承担运行风险，但这会将 PIE 放入非 Windows 系统的 `/usr/local/bin/pie`：

```shell
curl -fL --output /tmp/pie.phar https://github.com/php/pie/releases/latest/download/pie.phar \
  && gh attestation verify --owner php /tmp/pie.phar \
  && sudo mv /tmp/pie.phar /usr/local/bin/pie \
  && sudo chmod +x /usr/local/bin/pie
```

### Docker 安装

PIE 以纯二进制 Docker 镜像发布，因此您可以在 Docker 构建期间轻松安装：

```Dockerfile
COPY --from=ghcr.io/php/pie:bin /pie /usr/bin/pie
```

除了 `bin` 标签（代表最新的纯二进制镜像）外，您还可以使用明确的版本（格式为 `x.y.z-bin`）。使用 [GitHub registry](https://ghcr.io/php/pie) 查找可用标签。

> [!IMPORTANT]
> 纯二进制镜像不包含 PHP 运行时，因此您不能使用它们来_运行_ PIE。这只是分发 PHAR 文件的另一种方式，您仍然需要自己满足 PIE 的运行时要求。

#### 在 Dockerfile 中使用 PIE 的示例

这是如何在 Docker 镜像中使用 PIE 安装扩展的示例。注意，与 Composer 类似，您需要安装 `unzip` 或 [Zip](https://www.php.net/manual/zh/book.zip.php) 扩展，以便解压下载的软件包。

> [!NOTE]
> 以前只安装 `git` 也可以，因为解压失败时 Composer 会回退到从源代码检出。Composer 2.10 出于安全原因移除了该回退（[composer/composer#12885](https://github.com/composer/composer/pull/12885)），而 PIE 从 1.4.8 起使用 Composer 2.10，因此仅安装 `git` 已不再足够。对于 Composer 需要通过 git 读取的仓库（例如通过 `pie repository:add vcs ...` 添加的仓库），仍然需要 `git`。

```Dockerfile
FROM php:8.4-cli

# 添加 PIE 用于解压 .zip 文件的 `unzip` 包
RUN export DEBIAN_FRONTEND="noninteractive"; \
    set -eux; \
    apt-get update; apt-get install -y --no-install-recommends unzip; \
    rm -rf /var/lib/apt/lists/*

# 从最新的 `:bin` 发布版本复制 pie.phar
COPY --from=ghcr.io/php/pie:bin /pie /usr/bin/pie

# 使用 PIE 安装扩展...
RUN pie install asgrim/example-pie-extension
```

如果您想安装的扩展需要额外的库或其他依赖项，则必须事先安装这些依赖项。

## PIE 的先决条件

运行 PIE 需要 PHP 8.1 或更高版本。但是，您仍然可以使用 PIE 为较旧版本的 PHP 安装扩展。

除了 PHP，PIE 还需要系统上有以下工具才能下载、构建和安装扩展：

- 为运行 PIE 的 PHP 版本启用 `zip` 扩展，或使用 `git` 下载扩展源代码
- `autoconf`、`automake`、`libtool`、`m4`、`make` 和 `gcc` 来构建扩展
- PHP 开发工具（如 `php-config` 和 `phpize`）以准备构建扩展。

此外，每个扩展可能有自己的要求，例如额外的库。

> [!TIP]
> 如果在未安装正确先决条件的情况下运行 PIE，您可能会收到来自 *Box Requirements Checker* 的错误。如果您想尝试运行，请指定环境变量 `BOX_REQUIREMENT_CHECKER=0`。
>
> Linux 上的示例：
> ```shell
> $ BOX_REQUIREMENT_CHECKER=0 pie install foo/bar
> ```

### 使用 Linux

在基于 Debian 的系统上，您可以使用以下命令安装所需工具：

```shell
sudo apt-get install git autoconf automake libtool m4 make gcc
```

在基于 Red Hat 的系统上，您可以使用以下命令安装所需工具：

```shell
sudo yum install git autoconf automake libtool m4 make gcc
```

### 使用 macOS

在 macOS 上，您可以使用 [Homebrew](https://brew.sh) 安装所需工具：

```shell
brew install git autoconf automake libtool m4 make gcc
```

### 使用 Windows

在 Windows 上，扩展通常作为预编译的二进制文件分发。您无需自己构建扩展，而是将其作为 DLL 文件下载并放置在 PHP 扩展目录中。

## 下载、构建或安装扩展

PIE 能够：

 - 仅下载扩展，使用 `pie download ...`，
 - 下载并构建扩展，使用 `pie build ...`，
 - 或最常见的，下载、构建和安装扩展，使用 `pie install ...`

使用 PIE 安装扩展时，必须使用其 Composer 包名称。您可以在 [https://packagist.org/extensions](https://packagist.org/extensions) 上找到 PIE 兼容包的列表。

知道扩展名称后，您可以使用以下命令安装：

```shell
pie install <vendor>/<package>

# 例如：
pie install xdebug/xdebug
```

这将把 Xdebug 扩展安装到用于调用 PIE 的 PHP 版本中，使用与该 PHP 版本兼容的最新稳定版本的 Xdebug。

### 使用 PIE 为不同的 PHP 版本安装扩展

如果您试图为不同版本的 PHP 安装扩展，在非 Windows 系统上可以使用 `--with-php-config` 选项指定：

```shell
pie install --with-php-config=/usr/bin/php-config7.2 my/extension
```

在 Windows 上，您可以使用 `--with-php-path` 选项提供 `php` 可执行文件本身的路径。这是 Windows 上的一个示例，其中使用 PHP 8.1 运行 PIE，但我们想为 PHP 8.3 下载扩展：

```shell
> C:\php-8.1.7\php.exe C:\pie.phar install --with-php-path=C:\php-8.3.6\php.exe example/example-pie-extension
```

您可能还需要为目标 PHP 版本使用相应的 `phpize` 命令，可以使用 `--with-phpize-path` 选项指定：

```shell
pie install --with-phpize-path=/usr/bin/phpize7.2 my/extension
```

### 版本约束和稳定性

使用 PIE 安装扩展时，可以选择指定版本约束：

```bash
pie install <vendor>/<package>:<version-constraint>
```

如果给出 `version-constraint`，则尝试安装与允许版本匹配的该版本。版本约束使用与 Composer 相同的格式解析，以及最小稳定性。

* `^1.0` 将安装与 `1.0.0` 及以上版本向后兼容的最新稳定版本，根据语义化版本。
  [详见 Composer 文档](https://getcomposer.org/doc/articles/versions.md#caret-version-range-)。
* `^2.3@beta` 将安装与 `2.3.0` 及以上版本向后兼容的最新 beta 版本（例如 `2.3.0-beta.3`）。
* `dev-main` 将安装命令执行时 `main` 分支上的最新提交。这不适用于 Windows，因为没有带 Windows 二进制文件的发布版本。
* `dev-main#07f454ad797c30651be8356466685b15331f72ff` 将安装 `#` 后的提交 sha 表示的特定提交，在这种情况下将安装提交 `07f454ad797c30651be8356466685b15331f72ff`。这不适用于 Windows，因为没有带 Windows 二进制文件的发布版本。

当给出 `version-constraint` 时，它会被检查并直接添加到目标 PHP 版本的 `pie.json` 中，例如：

```shell
$ pie install "xdebug/xdebug:^3.4.3 || 3.4.1"
```

将在 `pie.json` 中设置以下内容：

```json
{
    "require": {
        "xdebug/xdebug": "^3.4.3 || 3.4.1"
    }
}
```

如果未给出 `version-constraint`，则尝试安装任何兼容的最新稳定版本。PIE 始终优先选择稳定版本。

### 指定配置选项

编译扩展时，某些扩展需要向 `./configure` 命令传递额外参数。这些参数通常用于启用或禁用某些功能，或提供未自动检测到的库路径。

要确定扩展可用的配置选项，可以使用 `pie info <vendor>/<package>`，它将返回一个列表，例如：

```text
Configure options:
    --enable-some-functionality  (whether to enable some additional functionality provided)
    --with-some-library-name=?  (Path for some-library)
```

然后可以使用无、部分或全部指定的配置选项安装上述示例扩展，一些示例：

```shell
pie install example/some-extension
pie install example/some-extension --enable-some-functionality
pie install example/some-extension --with-some-library-name=/path/to/the/lib
pie install example/some-extension --with-some-library-name=/path/to/the/lib --enable-some-functionality
```

### 配置 INI 文件

PIE 会自动尝试通过在适当的 INI 文件中添加 `extension=...` 或 `zend_extension=...` 来启用扩展。如果您想禁用此行为，请在 `pie install` 命令中传递 `--skip-enable-extension` 标志。尝试启用扩展时使用以下技术：

 * 如果使用 deb.sury.org 发行版，使用 `phpenmod`
 * 如果使用 Docker 的 PHP 镜像，使用 `docker-php-ext-enable`
 * 如果配置了"附加 .ini 文件"路径，则添加新文件到该路径
 * 如果配置了标准 php.ini，则追加到 php.ini

如果这些技术都不起作用，或者您使用了 `--skip-enable-extension` 标志，PIE 将警告您扩展未启用，并注意您必须自己启用扩展。

### 添加非 Packagist.org 仓库

有时您可能想从 Packagist.org 以外的包仓库（如 [Private Packagist](https://packagist.com/)）安装扩展，或从本地目录安装。由于 PIE 很大程度上基于 Composer，可以使用其他一些仓库类型：

* `pie repository:add [--with-php-config=...] path /path/to/your/local/extension`
* `pie repository:add [--with-php-config=...] vcs https://github.com/youruser/yourextension`
* `pie repository:add [--with-php-config=...] composer https://repo.packagist.com/your-private-packagist/`
* `pie repository:add [--with-php-config=...] composer packagist.org`

`repository:*` 命令都支持可选的 `--with-php-config` 标志，允许您指定要使用的 PHP 安装（例如，如果一台机器上有多个 PHP 安装）。上述添加的仓库也可以使用相反的 `repository:remove` 命令删除：

* `pie repository:remove [--with-php-config=...] /path/to/your/local/extension`
* `pie repository:remove [--with-php-config=...] https://github.com/youruser/yourextension`
* `pie repository:remove [--with-php-config=...] https://repo.packagist.com/your-private-packagist/`
* `pie repository:remove [--with-php-config=...] packagist.org`

注意，在 `repository:remove` 中不需要指定仓库类型，只需提供 URL。

您可以使用以下命令列出目标 PHP 安装的仓库：

* `pie repository:list [--with-php-config=...]`

## 检查并安装项目缺失的扩展

当在 PHP 项目工作目录中时，可以使用 `pie install` 来检查项目所需的扩展是否存在。如果缺少扩展，PIE 将尝试找到安装候选并以交互方式询问您是否要安装。例如：

```
$ pie install
🥧 PHP Installer for Extensions (PIE), 0.9.0, from The PHP Foundation
You are running PHP 8.3.19
Target PHP installation: 8.3.19 nts, on Linux/OSX/etc x86_64 (from /usr/bin/php8.3)
Checking extensions for your project your-vendor/your-project
requires: curl ✅ Already installed
requires: intl ✅ Already installed
requires: json ✅ Already installed
requires: example_pie_extension ⚠️  Missing

The following packages may be suitable, which would you like to install:
  [0] None
  [1] asgrim/example-pie-extension: Example PIE extension
 > 1
   > 🥧 PHP Installer for Extensions (PIE), 0.9.0, from The PHP Foundation
   > This command may need elevated privileges, and may prompt you for your password.
   > You are running PHP 8.3.19
   > Target PHP installation: 8.3.19 nts, on Linux/OSX/etc x86_64 (from /usr/bin/php8.3)
   > Found package: asgrim/example-pie-extension:2.0.2 which provides ext-example_pie_extension
   ... (snip) ...
   > ✅ Extension is enabled and loaded in /usr/bin/php8.3

Finished checking extensions.
```

## 与 PECL 的比较

由于 PIE 是 PECL 的替代品，这里是您可能熟悉的 PECL 命令与 PIE 中近似等效命令的比较。请注意，某些概念在 PIE 中有所不同或被省略，因为它们可能不适用于新工具。

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
| `pecl list-all`                | 访问 [Packagist 扩展列表](https://packagist.org/extensions)                                                             |
| `pecl list-channels`           | `pie repository:list`                                                                                                   |
| `pecl list-files`              |                                                                                                                         |
| `pecl list-upgrades`           |                                                                                                                         |
| `pecl login`                   |                                                                                                                         |
| `pecl logout`                  |                                                                                                                         |
| `pecl makerpm`                 |                                                                                                                         |
| `pecl package`                 | Linux - 仅标记发布版本。Windows - 使用 [`php/php-windows-builder` action](https://github.com/php/php-windows-builder)   |
| `pecl package-dependencies`    |                                                                                                                         |
| `pecl package-validate`        | 在您的扩展检出中：`composer validate`                                                                                   |
| `pecl pickle`                  |                                                                                                                         |
| `pecl remote-info xdebug`      | `pie info xdebug/xdebug`                                                                                                |
| `pecl remote-list`             | 访问 [Packagist 扩展列表](https://packagist.org/extensions)                                                             |
| `pecl run-scripts`             |                                                                                                                         |
| `pecl run-tests`               |                                                                                                                         |
| `pecl search`                  | 访问 [Packagist 扩展列表](https://packagist.org/extensions)                                                             |
| `pecl shell-test`              |                                                                                                                         |
| `pecl sign`                    |                                                                                                                         |
| `pecl svntag`                  |                                                                                                                         |
| `pecl uninstall`               |                                                                                                                         |
| `pecl update-channels`         |                                                                                                                         |
| `pecl upgrade xdebug`          | `pie install xdebug/xdebug`                                                                                             |
| `pecl upgrade-all`             |                                                                                                                         |

