---
title: 拡張機能メンテナー
order: 3
---
# PIE 拡張機能メンテナーガイド

## PIE のビルドとインストール手順

### 非 Windows（例：Linux、OSX など）

PIE は通常の [PHP 拡張機能のビルドとインストールプロセス](https://www.php.net/manual/ja/install.pecl.phpize.php)に従います：

 * `phpize` で PHP API パラメータを設定します。拡張機能をインストールする人は、`phpize` がパスにない場合 `--with-phpize-path` を指定できます。
 * `./configure` で特定のシステムのビルドパラメータとライブラリを設定します。拡張機能をインストールする人は、`composer.json` で指定した設定オプションを指定できます。これを行う方法については、[設定オプション](#configure-options)ドキュメントを参照してください。
 * `make` で実際に拡張機能をビルドします。これは実行する並列プロセスの数を自動的に検出しようとしますが、インストールする人は `--make-parallel-jobs N` または `-jN` オプションでこれをオーバーライドできます。
 * `make install` で設定された PHP インストールに拡張機能をインストールします。PIE がインストール場所への書き込み権限を持っていない場合、`sudo` で権限を昇格しようとします。

これは、PIE を実行しているシステムに適切なビルドツールがインストールされている必要があることを意味します。拡張機能のビルドと PHP の内部動作に関する有用なリソースは [PHP Internals Book](https://www.phpinternalsbook.com/) です。

### Windows

Windows システムの場合、拡張機能メンテナーはプリビルドされたバイナリを提供する必要があります。PIE 用に正しい方法でこれを行う方法の詳細については、以下の [Windows サポート](#windows-support)セクションを参照してください。

## 拡張機能に PIE サポートを追加する方法

拡張機能に PIE サポートを追加することは比較的簡単で、通常の PHP パッケージを Packagist に追加するのと非常に似ています。

### すでに PECL にある拡張機能

既存の PECL 拡張機能のメンテナーの場合、以下はコンテキストのための有用な情報です：

 - すでに PECL にある拡張機能の場合、PECL への公開を続けたくない場合、`package.xml` は不要です。今すぐ PECL への公開を続けたい場合は、`package.xml` を維持し続けることができます。
 - `package.xml` は各リリースを明示的にリストします。PIE では、Packagist が通常の Composer パッケージと同じ方法でタグまたはブランチエイリアスを取得するため、これは不要になりました。これは、パッケージをリリースするには、タグとリリースをプッシュする必要があることを意味します。
 - デフォルトの設定では、パッケージの内容はリリースのタグまたはリビジョンの [Git archive](https://git-scm.com/docs/git-archive) によって決定されます。[export-ignore](https://git-scm.com/docs/git-archive#Documentation/git-archive.txt-export-ignore) 属性を使用して、アーカイブからファイルとパスを除外できます。

### 拡張機能に `composer.json` を追加

PIE サポートを追加する最初のステップは、拡張機能リポジトリに `composer.json` を追加することです。ほとんどの典型的なフィールドは通常の Composer パッケージと同じですが、いくつかの注目すべき例外があります：

 * `type` は PHP モジュールの場合は `php-ext`（これがほとんどの拡張機能になります）、または Zend 拡張機能の場合は `php-ext-zend` である必要があります。
 * 追加の `php-ext` セクションが存在する可能性があります（`php-ext` 内に含めることができるディレクティブについては以下を参照）
 * Composer パッケージ名（つまりトップレベルの `name` フィールド）は、通常の Composer パッケージ名形式に従う必要があります。つまり `<vendor>/<package>` です。
 * ただし、PIE 拡張機能の Composer パッケージ名は、`type` フィールドが異なる場合でも、通常の PHP パッケージと同じ Composer パッケージ名を共有してはなりません。

#### `php-ext` 定義

##### `extension-name`

`extension-name` を指定することができ、[\Php\Pie\ExtensionName::VALID_PACKAGE_NAME_REGEX](../src/ExtensionName.php) で定義されている通常の拡張機能名正規表現に準拠する必要があります。`extension-name` が指定されていない場合、`extension-name` は Composer パッケージ名から派生し、ベンダープレフィックスが削除されます。例えば、次の `composer.json` の場合：

```json
{
    "name": "myvendor/myextension"
}
```

拡張機能名は `myextension` として派生されます。`myvendor/` ベンダープレフィックスは削除されます。

> [!WARNING]
> Composer パッケージ名が無効な PHP 拡張機能名になる場合、`extension-name` ディレクティブを指定する必要があります。例えば、Composer パッケージ名 `myvendor/my-extension` はハイフンが許可されていないため無効な PHP 拡張機能名になるため、この Composer パッケージ名には有効な `extension-name` を指定する必要があります。

`extension-name` は、Composer で `require` を使用する際の慣例である `ext-` でプレフィックスを付けるべきではありません。

`extension-name` の使用例：

```json
{
    "name": "xdebug/xdebug",
    "php-ext": {
        "extension-name": "xdebug"
    }
}
```

##### `priority`

`priority` は `ini` ファイル名の一部を形成し、ターゲットプラットフォームがディレクトリ内の複数の INI ファイルを使用する場合、拡張機能の順序を制御します。

##### `support-zts` と `support-nts`

拡張機能が Zend Thread-Safe（ZTS）および非スレッドセーフ（NTS）モードをサポートするかどうかを示します。これらのフラグは指定されていない場合、両方ともデフォルトで `true` ですが、拡張機能がいずれかのモードをサポートしていない場合は指定する必要があり、ターゲットプラットフォームに拡張機能をインストールできないことを意味します。

理論的には、`support-zts` と `support-nts` の両方に `false` を指定することは可能ですが、これはパッケージをどこにもインストールできないことを意味するため、推奨されません。

##### `configure-options`

これは `./configure` コマンドに渡すことができるパラメータのリストです。リストの各項目は次の JSON オブジェクトです：

 * `name`、パラメータ名自体
 * `description`、パラメータが何をするかの役立つ説明
 * オプションで `needs-value`、PIE にパラメータが単純なフラグ（通常 `--enable-this-flag` タイプのパラメータに使用）か、パラメータに値を指定する必要があるか（通常 `--with-library-path=...` タイプのパラメータに使用され、エンドユーザーが値を指定する必要がある）を伝えるブール値

エンドユーザーが PIE で拡張機能をインストールする場合、`./configure` に渡される定義済みの `configure-options` を指定できます。例えば、拡張機能が次の `composer.json` を定義している場合：

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

次のように `pie build` または `pie install` コマンドを呼び出して、目的の設定を実現できます：

 * `pie install myvendor/myext`
   * これは単にパラメータなしで `./configure` を呼び出します
 * `pie install myvendor/myext --enable-my-flag`
   * これは `./configure --enable-my-flag` を呼び出します
 * `pie install myvendor/myext --with-some-lib=/path/to/somelib`
   * これは `./configure --with-some-lib=/path/to/somelib` を呼び出します
 * `pie install myvendor/myext --enable-my-flag --with-some-lib=/path/to/somelib`
   * これは `./configure --enable-my-flag --with-some-lib=/path/to/somelib` を呼び出します

PIE のエンドユーザーは、拡張機能の `configure-options` 定義で定義されていない設定オプションを指定することはできません。上記と同じ `composer.json` の例を使用して、無効なオプションで PIE を呼び出す場合、例えば `pie install myvendor/myext --something-else` はエラー `The "--something-else" option does not exist.` になります。

エンドユーザーが `configure-options` 定義で定義されたフラグを指定しない場合、それは `./configure` に渡されません。`configure-options` 定義でデフォルト値を指定する方法はありません。`config.m4` でこれに応じて処理する必要があります。

##### `build-path`

ソースコードがリポジトリのルートにない場合、`build-path` 設定を使用できます。例えば、リポジトリ構造が次のような場合：

```text
/
  docs/
  src/
    config.m4
    config.w32
    myext.c
    ...etc
```

この場合、実際の拡張機能ソースコードは `src/` でビルドされるため、`build-path` でこのパスを指定する必要があります：

```json
{
    "name": "myvendor/myext",
    "php-ext": {
        "extension-name": "myext",
        "build-path": "src"
    }
}
```

`build-path` には、置き換えられるテンプレート値を含めることができます：

 * `{version}` はパッケージバージョンに置き換えられます。例えば、バージョン 1.2.3 のパッケージで `build-path` が `myext-{version}` の場合、実際のビルドパスは `myext-1.2.3` になります。

##### `download-url-method`

`download-url-method` ディレクティブにより、拡張機能メンテナーはソースパッケージのダウンロード動作を変更できます。

 * これを `composer-default` に設定すると（指定されていない場合のデフォルト値）、Composer によって実装されたデフォルトの動作が使用されます。これは、GitHub API（または他のソースコントロールシステム）からの標準 ZIP アーカイブを使用することです。
 * `pre-packaged-source` を使用すると、次の命名規則のいずれかに一致するリリースアセットリストでソースコードパッケージを見つけます：
   * `php_{ExtensionName}-{Version}-src.tgz`（例：`php_myext-1.20.1-src.tgz`）
   * `php_{ExtensionName}-{Version}-src.zip`（例：`php_myext-1.20.1-src.zip`）
   * `{ExtensionName}-{Version}.tgz`（これは PECL パッケージとの後方互換性のため）

##### `os-families` 制限

`os-families` および `os-families-exclude` ディレクティブにより、拡張機能メンテナーはオペレーティングシステムの互換性を制限できます。

 * `os-families` 拡張機能と互換性があるとマークする OS ファミリーの配列。（例：`"os-families": ["windows"]` は Windows でのみ利用可能な拡張機能）
 * `os-families-exclude` 拡張機能と互換性がないとマークする OS ファミリーの配列。（例：`"os-families-exclude": ["windows"]` は Windows にインストールできない拡張機能）

受け入れられる OS ファミリーのリスト："windows"、"bsd"、"darwin"、"solaris"、"linux"、"unknown"

> [!WARNING]
> `os-families` と `os-families-exclude` のうち 1 つだけを定義できます。

#### 拡張機能の依存関係

拡張機能作成者は `require` でいくつかの依存関係を定義できますが、実際には、ほとんどの拡張機能は、拡張機能がサポートする PHP バージョンを除いて、依存関係を定義する必要はありません。他の拡張機能への依存関係（例：`ext-json`）を定義できます。ただし、通常の PHP パッケージ（例：`monolog/monolog`）への依存関係は `require` セクションで指定すべきではありません。

拡張機能が別の依存関係への依存関係を定義し、これが利用できない場合、拡張機能をインストールする人は次のようなメッセージを受け取ります：

```
Cannot use myvendor/myextension's latest version 1.2.3 as it requires
ext-something * which is missing from your platform.
```

#### 拡張機能が動作するかどうかの確認

まず、`composer validate` を使用して `composer.json` が正しくフォーマットされているか確認できます：

```shell
$ composer validate
./composer.json is valid
```

次に、拡張機能のディレクトリで `pie install` を使用して拡張機能をインストールできます：

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

##### インストールせずにビルドのみ

アプリケーションのビルドをテストするだけで、ターゲット PHP バージョンにインストールしない場合、まず拡張機能ディレクトリを「path」タイプのリポジトリとして追加する必要があります：

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

次に、ビルドをテストできます：

```shell
$ pie build asgrim/example-pie-extension:*@dev
```

> [!TIP]
> 拡張機能がまだ Packagist に公開されていないため、バージョン制約として `*@dev` を指定する必要があります。そうしないと、デフォルトの安定性が `stable` であるため、PIE は拡張機能を見つけられません。

### Packagist に拡張機能を提出

`composer.json` をリポジトリにコミットしたら、他のパッケージと同じように Packagist に提出できます。

 * [https://packagist.org/packages/submit](https://packagist.org/packages/submit) にアクセス
 * リポジトリの URL を入力し、指示に従ってください。

### Windows サポート

Windows ユーザーをサポートするには、PIE は現在 DLL をオンザフライでビルドすることをサポートしていないため、プリビルドされた DLL を公開する必要があります。Windows 互換リリースの予想されるワークフローは次のとおりです：

 - GitHub でリリースが行われます（現時点では GitHub のみサポート）
 - CI パイプラインが実行され、リリースアセットをビルドします（例：GitHub Action）
 - 結果のビルドアセットが ZIP ファイルとして GitHub リリースに公開されます

ZIP ファイルの名前とその中に含まれる DLL は次のようにする必要があります：

* `php_{extension-name}-{tag}-{php-maj/min}-{ts|nts}-{compiler}-{arch}.zip`
* 例：`php_xdebug-3.3.2-8.3-ts-vs16-x86_64.zip`

これらの項目の説明：

* `extension-name` 拡張機能名、例：`xdebug`
* `tag` 例：`3.3.0alpha3` - 作成したタグ/リリースで定義
* `php-maj/min` - 例：PHP 8.3.* の場合は `8.3`
* `compiler` - 通常 `vc6`、`vs16` のようなもの - `php -i` の 'PHP Extension Build' フラグから取得
* `ts|nts` - スレッドセーフまたは非スレッドセーフ
* `arch` - 例：`x86_64`
   * Windows：`php -i` の `Architecture` からヒントを使用（下記参照）
   * 非 Windows：`PHP_INT_SIZE` を確認 - 32 ビットの場合は 4、64 ビットの場合は 8

アーキテクチャ名は正規化が必要な場合があります。異なるプラットフォームでアーキテクチャの名前が異なるためです。PIE は次の正規化されたアーキテクチャを期待します：

 * `x86_64`（`x64`、`x86_64`、`AMD64` から正規化）
 * `arm64`（`arm64` から正規化）
 * `x86`（その他の値）

最新のマップ（ドキュメントが最新でない場合）については、`\Php\Pie\Platform\Architecture::parseArchitecture` を確認してください。

#### Windows ZIP の内容

プリビルドされた ZIP には、ZIP 自体と同じ名前の DLL が少なくとも含まれている必要があります。例：`php_{extension-name}-{tag}-{php-maj/min}-{ts|nts}-{compiler}-{arch}.dll`。`.dll` は PHP 拡張機能パスに移動され、名前が変更されます。例：`C:\path\to\php\ext\php_{extension-name}.dll` に移動されます。ZIP ファイルには次のような追加のリソースが含まれる場合があります：

* `php_{extension-name}-{tag}-{php-maj/min}-{ts|nts}-{compiler}-{arch}.pdb` - これは `C:\path\to\php\ext\php_{extension-name}.dll` の隣に移動されます
* `*.dll` - 他の `.dll` は `C:\path\to\php\php.exe` の隣に移動されます
* その他のファイルは `C:\path\to\php\extras\{extension-name}\.` に移動されます

#### Windows 公開の自動化

PHP は、拡張機能メンテナーが Windows 互換アセットをビルドおよびリリースできるようにする [一連の GitHub Actions](https://github.com/php/php-windows-builder) を提供しています。これらのアクションを使用するワークフローの例：

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

出典：[https://github.com/php/php-windows-builder?tab=readme-ov-file#example-workflow-to-build-and-release-an-extension](https://github.com/php/php-windows-builder?tab=readme-ov-file#example-workflow-to-build-and-release-an-extension)

