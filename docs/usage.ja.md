---
title: PIE の使用
order: 2
---
# PIE 使用ガイド

## PIE のインストール

### 手動インストール

- [最新リリース](https://github.com/php/pie/releases)から `pie.phar` をダウンロード
- `gh attestation verify --owner php pie.phar` で PHAR のソースを検証
    - このステップには [`gh` CLI コマンド](https://github.com/cli/cli/)が必要です。
- `php pie.phar <command>` で PIE を呼び出すことができます
- オプション：`pie.phar` を `$PATH` にコピー、例：`cp pie.phar /usr/local/bin/pie`
    - PIE を `$PATH` にコピーした場合、`pie <command>` で PIE を呼び出すことができます

このドキュメントでは、`pie.phar` を `$PATH` に移動したと仮定しています。例えば、非 Windows システムでは `/usr/local/bin/pie` です。

### ワンライナー

これは署名を検証しないため、実行するリスクはご自身で負担することになりますが、非 Windows システムで PIE を `/usr/local/bin/pie` に配置します：

```shell
curl -fL --output /tmp/pie.phar https://github.com/php/pie/releases/latest/download/pie.phar \
  && gh attestation verify --owner php /tmp/pie.phar \
  && sudo mv /tmp/pie.phar /usr/local/bin/pie \
  && sudo chmod +x /usr/local/bin/pie
```

### Docker インストール

PIE はバイナリのみの Docker イメージとして公開されているため、Docker ビルド中に簡単にインストールできます：

```Dockerfile
COPY --from=ghcr.io/php/pie:bin /pie /usr/bin/pie
```

`bin` タグ（最新のバイナリのみのイメージを表す）の代わりに、明示的なバージョン（`x.y.z-bin` 形式）を使用することもできます。利用可能なタグを見つけるには [GitHub registry](https://ghcr.io/php/pie) を使用してください。

> [!IMPORTANT]
> バイナリのみのイメージには PHP ランタイムが含まれていないため、PIE を_実行_することはできません。これは PHAR ファイルを配布する別の方法に過ぎず、PIE のランタイム要件は自分で満たす必要があります。

#### Dockerfile での PIE の動作例

これは Docker イメージ内で PIE を使用して拡張機能をインストールする方法の例です。Composer と同様に、`unzip`、[Zip](https://www.php.net/manual/ja/book.zip.php) 拡張機能、または `git` のようなものが必要です。

```Dockerfile
FROM php:8.4-cli

# PIE が .zip ファイルを展開するために使用する `unzip` パッケージを追加
RUN export DEBIAN_FRONTEND="noninteractive"; \
    set -eux; \
    apt-get update; apt-get install -y --no-install-recommends unzip; \
    rm -rf /var/lib/apt/lists/*

# 最新の `:bin` リリースから pie.phar をコピー
COPY --from=ghcr.io/php/pie:bin /pie /usr/bin/pie

# PIE を使用して拡張機能をインストール...
RUN pie install asgrim/example-pie-extension
```

インストールしたい拡張機能に追加のライブラリや他の依存関係が必要な場合は、それらを事前にインストールする必要があります。

## PIE の前提条件

PIE の実行には PHP 8.1 以降が必要です。ただし、古いバージョンの PHP 用の拡張機能をインストールするために PIE を使用することはできます。

PHP に加えて、PIE は拡張機能をダウンロード、ビルド、インストールするために、システムに以下のツールが必要です：

- PIE を実行する PHP バージョンで有効化された `zip` 拡張機能、または拡張機能のソースコードをダウンロードするための `git`
- 拡張機能をビルドするための `autoconf`、`automake`、`libtool`、`m4`、`make`、`gcc`
- 拡張機能のビルドを準備するための PHP 開発ツール（`php-config` や `phpize` など）

また、各拡張機能には独自の要件（追加のライブラリなど）がある場合があります。

> [!TIP]
> 正しい前提条件がインストールされていない状態で PIE を実行すると、*Box Requirements Checker* からエラーが表示される場合があります。とにかく実行を試みたい場合は、環境変数 `BOX_REQUIREMENT_CHECKER=0` を指定してください。
>
> Linux での例：
> ```shell
> $ BOX_REQUIREMENT_CHECKER=0 pie install foo/bar
> ```

### Linux の使用

Debian ベースのシステムでは、以下のコマンドで必要なツールをインストールできます：

```shell
sudo apt-get install git autoconf automake libtool m4 make gcc
```

Red Hat ベースのシステムでは、以下のコマンドで必要なツールをインストールできます：

```shell
sudo yum install git autoconf automake libtool m4 make gcc
```

### macOS の使用

macOS では、[Homebrew](https://brew.sh) を使用して必要なツールをインストールできます：

```shell
brew install git autoconf automake libtool m4 make gcc
```

### Windows の使用

Windows では、拡張機能は通常、プリコンパイル済みのバイナリとして配布されます。拡張機能を自分でビルドする代わりに、DLL ファイルとしてダウンロードされ、PHP 拡張機能ディレクトリに配置されます。

## 拡張機能のダウンロード、ビルド、またはインストール

PIE には以下の機能があります：

 - `pie download ...` で拡張機能のみをダウンロード
 - `pie build ...` で拡張機能をダウンロードしてビルド
 - または、最も一般的な、`pie install ...` で拡張機能をダウンロード、ビルド、インストール

PIE で拡張機能をインストールする場合、その Composer パッケージ名を使用する必要があります。PIE 互換パッケージのリストは [https://packagist.org/extensions](https://packagist.org/extensions) で確認できます。

拡張機能名がわかったら、以下のようにインストールできます：

```shell
pie install <vendor>/<package>

# 例：
pie install xdebug/xdebug
```

これにより、PIE を呼び出すために使用された PHP バージョンに Xdebug 拡張機能がインストールされ、その PHP バージョンと互換性のある Xdebug の最新安定版が使用されます。

### 異なる PHP バージョン用の拡張機能を PIE でインストール

異なるバージョンの PHP 用の拡張機能をインストールしようとしている場合、非 Windows システムでは `--with-php-config` オプションで指定できます：

```shell
pie install --with-php-config=/usr/bin/php-config7.2 my/extension
```

Windows では、`--with-php-path` オプションを使用して `php` 実行可能ファイル自体のパスを指定できます。これは Windows での例で、PHP 8.1 を使用して PIE を実行していますが、PHP 8.3 用の拡張機能をダウンロードしたい場合です：

```shell
> C:\php-8.1.7\php.exe C:\pie.phar install --with-php-path=C:\php-8.3.6\php.exe example/example-pie-extension
```

ターゲット PHP バージョンに対応する `phpize` コマンドを使用する必要がある場合もあります。これは `--with-phpize-path` オプションで指定できます：

```shell
pie install --with-phpize-path=/usr/bin/phpize7.2 my/extension
```

### バージョン制約と安定性

PIE で拡張機能をインストールする際に、オプションでバージョン制約を指定できます：

```bash
pie install <vendor>/<package>:<version-constraint>
```

`version-constraint` が指定された場合、許可されたバージョンと一致する場合、そのバージョンをインストールしようとします。バージョン制約は Composer と同じ形式で解決され、最小安定性も考慮されます。

* `^1.0` は、セマンティックバージョニングに従って、`1.0.0` 以上で後方互換性のある最新の安定版をインストールします。
  [詳細は Composer ドキュメントを参照](https://getcomposer.org/doc/articles/versions.md#caret-version-range-)。
* `^2.3@beta` は、`2.3.0` 以上で後方互換性のある最新のベータ版をインストールします（例：`2.3.0-beta.3`）。
* `dev-main` は、コマンド実行時の `main` ブランチの最新コミットをインストールします。これは Windows バイナリを含むリリースがないため、Windows では機能しません。
* `dev-main#07f454ad797c30651be8356466685b15331f72ff` は、`#` の後のコミット sha で示される特定のコミットをインストールします。この場合、コミット `07f454ad797c30651be8356466685b15331f72ff` がインストールされます。これは Windows バイナリを含むリリースがないため、Windows では機能しません。

`version-constraint` が指定されると、チェックされ、ターゲット PHP バージョンの `pie.json` に直接追加されます。例：

```shell
$ pie install "xdebug/xdebug:^3.4.3 || 3.4.1"
```

これにより、`pie.json` に以下が設定されます：

```json
{
    "require": {
        "xdebug/xdebug": "^3.4.3 || 3.4.1"
    }
}
```

`version-constraint` が指定されていない場合、互換性のある最新の安定版をインストールしようとします。PIE は常に安定版を優先します。

### 設定オプションの指定

拡張機能をコンパイルする際、一部の拡張機能では `./configure` コマンドに追加のパラメータを渡す必要があります。これらは通常、特定の機能を有効化または無効化するため、または自動検出されないライブラリのパスを提供するために使用されます。

拡張機能で利用可能な設定オプションを確認するには、`pie info <vendor>/<package>` を使用します。これにより、次のようなリストが返されます：

```text
Configure options:
    --enable-some-functionality  (whether to enable some additional functionality provided)
    --with-some-library-name=?  (Path for some-library)
```

上記の例の拡張機能は、指定された設定オプションなし、一部、またはすべてを使用してインストールできます。いくつかの例：

```shell
pie install example/some-extension
pie install example/some-extension --enable-some-functionality
pie install example/some-extension --with-some-library-name=/path/to/the/lib
pie install example/some-extension --with-some-library-name=/path/to/the/lib --enable-some-functionality
```

### INI ファイルの設定

PIE は、適切な INI ファイルに `extension=...` または `zend_extension=...` を追加することで、拡張機能を自動的に有効化しようとします。この動作を無効にしたい場合は、`pie install` コマンドに `--skip-enable-extension` フラグを渡してください。拡張機能を有効化しようとする際には、以下の技術が使用されます：

 * deb.sury.org ディストリビューションを使用している場合は `phpenmod`
 * Docker の PHP イメージを使用している場合は `docker-php-ext-enable`
 * 「追加の .ini ファイル」パスが設定されている場合は、そのパスに新しいファイルを追加
 * 標準の php.ini が設定されている場合は、php.ini に追記

これらの技術がいずれも機能しない場合、または `--skip-enable-extension` フラグを使用した場合、PIE は拡張機能が有効化されなかったことを警告し、拡張機能を自分で有効化する必要があることを通知します。

### Packagist.org 以外のリポジトリの追加

Packagist.org 以外のパッケージリポジトリ（[Private Packagist](https://packagist.com/) など）から拡張機能をインストールしたい場合や、ローカルディレクトリからインストールしたい場合があります。PIE は Composer をベースにしているため、他のリポジトリタイプを使用できます：

* `pie repository:add [--with-php-config=...] path /path/to/your/local/extension`
* `pie repository:add [--with-php-config=...] vcs https://github.com/youruser/yourextension`
* `pie repository:add [--with-php-config=...] composer https://repo.packagist.com/your-private-packagist/`
* `pie repository:add [--with-php-config=...] composer packagist.org`

`repository:*` コマンドはすべて、オプションの `--with-php-config` フラグをサポートしており、使用する PHP インストールを指定できます（例えば、1 台のマシンに複数の PHP インストールがある場合）。追加されたリポジトリは、逆の `repository:remove` コマンドを使用して削除することもできます：

* `pie repository:remove [--with-php-config=...] /path/to/your/local/extension`
* `pie repository:remove [--with-php-config=...] https://github.com/youruser/yourextension`
* `pie repository:remove [--with-php-config=...] https://repo.packagist.com/your-private-packagist/`
* `pie repository:remove [--with-php-config=...] packagist.org`

`repository:remove` ではリポジトリタイプを指定する必要はなく、URL だけを指定します。

ターゲット PHP インストールのリポジトリをリストするには：

* `pie repository:list [--with-php-config=...]`

## プロジェクトに不足している拡張機能の確認とインストール

PHP プロジェクトの作業ディレクトリにいる場合、`pie install` を使用してプロジェクトが必要とする拡張機能が存在するかどうかを確認できます。拡張機能が不足している場合、PIE はインストール候補を見つけて、インストールするかどうかをインタラクティブに尋ねます。例：

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

## PECL との比較

PIE は PECL の代替品であるため、PECL でよく知られているコマンドと PIE での同等のコマンドの比較を以下に示します。一部の概念は PIE では異なるか省略されている場合があります。これは新しいツールには適用されない可能性があるためです。

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
| `pecl list-all`                | [Packagist 拡張機能リスト](https://packagist.org/extensions)を参照                                                       |
| `pecl list-channels`           | `pie repository:list`                                                                                                   |
| `pecl list-files`              |                                                                                                                         |
| `pecl list-upgrades`           |                                                                                                                         |
| `pecl login`                   |                                                                                                                         |
| `pecl logout`                  |                                                                                                                         |
| `pecl makerpm`                 |                                                                                                                         |
| `pecl package`                 | Linux - リリースをタグ付けするだけ。Windows - [`php/php-windows-builder` action](https://github.com/php/php-windows-builder) を使用 |
| `pecl package-dependencies`    |                                                                                                                         |
| `pecl package-validate`        | 拡張機能のチェックアウトで：`composer validate`                                                                          |
| `pecl pickle`                  |                                                                                                                         |
| `pecl remote-info xdebug`      | `pie info xdebug/xdebug`                                                                                                |
| `pecl remote-list`             | [Packagist 拡張機能リスト](https://packagist.org/extensions)を参照                                                       |
| `pecl run-scripts`             |                                                                                                                         |
| `pecl run-tests`               |                                                                                                                         |
| `pecl search`                  | [Packagist 拡張機能リスト](https://packagist.org/extensions)を参照                                                       |
| `pecl shell-test`              |                                                                                                                         |
| `pecl sign`                    |                                                                                                                         |
| `pecl svntag`                  |                                                                                                                         |
| `pecl uninstall`               |                                                                                                                         |
| `pecl update-channels`         |                                                                                                                         |
| `pecl upgrade xdebug`          | `pie install xdebug/xdebug`                                                                                             |
| `pecl upgrade-all`             |                                                                                                                         |

