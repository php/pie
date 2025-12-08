# 🥧 PIE (PHP 扩展安装器)

## 什么是 PIE？

PIE 是一个新的 PHP 扩展安装器，旨在最终替代 PECL。
它以 [PHAR](https://www.php.net/manual/en/intro.phar.php) 格式分发，
就像 Composer 一样，工作方式也类似，但它将 PHP 扩展（PHP 模块或 Zend 扩展）
安装到您的 PHP 安装中，而不是将 PHP 包拉入您的项目或库。

# 使用 PIE - 我需要什么才能开始？

## 先决条件

您需要 PHP 8.1 或更高版本来运行 PIE，但 PIE 可以将扩展安装到任何其他已安装的 PHP 版本。

在 Linux 上，您需要安装构建工具链。在 Debian/Ubuntu 类型的系统上，您可以运行：

```shell
sudo apt install gcc make autoconf libtool bison re2c pkg-config php-dev
```

在 Windows 上，您不需要安装任何构建工具链，因为 Windows 的 PHP 扩展
以预编译包的形式分发，包含扩展 DLL。

## 安装 PIE

- 下载 `pie.phar`，可以通过以下方式：
  - [最新稳定版本](https://github.com/php/pie/releases)
  - [最新不稳定夜间构建版本](https://php.github.io/pie/pie-nightly.phar)
- 使用 `gh attestation verify --owner php pie.phar` 验证 PHAR 的来源
- 然后您可以使用 `php pie.phar <command>` 调用 PIE

更多安装详情可以在 [使用文档](./docs/usage.md) 中找到。
本文档假设您已将 `pie.phar` 移动到您的 `$PATH` 中，例如在非 Windows 系统上为 `/usr/local/bin/pie`。

## 使用 PIE 安装单个扩展

您可以使用 `install` 命令安装扩展。例如，要安装 `example_pie_extension` 扩展，您可以运行：

```shell
$ pie install example/example-pie-extension
此命令可能需要提升权限，并可能提示您输入密码。
您正在运行 PHP 8.3.10
目标 PHP 安装：8.3.10 nts，在 Linux/OSX/etc x86_64 上（来自 /usr/bin/php8.3）
找到包：example/example-pie-extension:1.0.1，提供 ext-example_pie_extension
phpize 完成。
配置完成。
构建完成：/tmp/pie_downloader_66e0b1de73cdb6.04069773/example-example-pie-extension-769f906/modules/example_pie_extension.so
安装完成：/usr/lib/php/20230831/example_pie_extension.so
您现在必须在 php.ini 中添加 "extension=example_pie_extension"
$
```

## 为 PHP 项目安装所有扩展

在您的 PHP 项目中，您可以安装任何缺失的顶级扩展：

```
$ pie install
🥧 PHP Installer for Extensions (PIE), 0.9.0, from The PHP Foundation
您正在运行 PHP 8.3.19
目标 PHP 安装：8.3.19 nts，在 Linux/OSX/etc x86_64 上（来自 /usr/bin/php8.3）
检查您的项目 your-vendor/your-project 的扩展
requires: curl ✅ 已安装
requires: intl ✅ 已安装
requires: json ✅ 已安装
requires: example_pie_extension ⚠️  缺失

以下包可能合适，您想安装哪个：
  [0] 无
  [1] asgrim/example-pie-extension: Example PIE extension
 > 1
   > 🥧 PHP Installer for Extensions (PIE), 0.9.0, from The PHP Foundation
   > 此命令可能需要提升权限，并可能提示您输入密码。
   > 您正在运行 PHP 8.3.19
   > 目标 PHP 安装：8.3.19 nts，在 Linux/OSX/etc x86_64 上（来自 /usr/bin/php8.3）
   > 找到包：asgrim/example-pie-extension:2.0.2，提供 ext-example_pie_extension
   ... (省略) ...
   > ✅ 扩展已在 /usr/bin/php8.3 中启用并加载

完成扩展检查。
```

## 支持 PIE 的扩展

支持 PIE 的扩展列表可以在
[https://packagist.org/extensions](https://packagist.org/extensions) 上找到。

# 我有错误、功能想法、问题、需要帮助等。

 - 如果您有想法、问题或需要帮助，请使用 [讨论区](https://github.com/php/pie/discussions)。
   - **在贡献新功能之前，请先与我们联系** - 这可能是
     我们已经正在开发的内容，因为 PHP Foundation 也在积极
     开发这个项目，并且已经有一些新功能正在开发中...
 - 如果您有错误要报告，请使用 [问题](https://github.com/php/pie/issues)。

# 我是扩展维护者 - 如何添加 PIE 支持？

如果您是想要为您的扩展添加 PIE 支持的扩展维护者，
请阅读 [扩展维护者文档](./docs/extension-maintainers.md)。

## 更多文档...

PIE 的完整文档可以在 [使用文档](./docs/usage.md) 中找到。

