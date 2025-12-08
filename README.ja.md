# 🥧 PIE (PHP 拡張機能インストーラー)

## PIE とは？

PIE は PHP 拡張機能の新しいインストーラーで、最終的に PECL を置き換えることを目的としています。
[PHAR](https://www.php.net/manual/en/intro.phar.php) として配布され、
Composer と同様に動作しますが、PHP パッケージをプロジェクトやライブラリに
取り込むのではなく、PHP 拡張機能（PHP モジュールまたは Zend 拡張機能）を
PHP インストールにインストールします。

# PIE の使用 - 始めるために何が必要ですか？

## 前提条件

PIE を実行するには PHP 8.1 以降が必要ですが、PIE は他のインストール済み PHP バージョンに
拡張機能をインストールできます。

Linux では、ビルドツールチェーンをインストールする必要があります。Debian/Ubuntu タイプの
システムでは、次のようなコマンドを実行できます：

```shell
sudo apt install gcc make autoconf libtool bison re2c pkg-config php-dev
```

Windows では、ビルドツールチェーンをインストールする必要はありません。Windows の PHP 拡張機能は
拡張機能 DLL を含むプリコンパイル済みパッケージとして配布されるためです。

## PIE のインストール

- `pie.phar` をダウンロードします：
  - [最新の安定版リリース](https://github.com/php/pie/releases)
  - [最新の不安定なナイトリービルド](https://php.github.io/pie/pie-nightly.phar)
- `gh attestation verify --owner php pie.phar` で PHAR の出所を確認します
- その後、`php pie.phar <command>` で PIE を呼び出すことができます

インストールの詳細については、[使用法](./docs/usage.md) ドキュメントを参照してください。
このドキュメントでは、`pie.phar` を `$PATH` に移動したことを前提としています（例：非 Windows システムでは `/usr/local/bin/pie`）。

## PIE を使用して単一の拡張機能をインストール

`install` コマンドを使用して拡張機能をインストールできます。例えば、`example_pie_extension` 拡張機能を
インストールするには、次を実行します：

```shell
$ pie install example/example-pie-extension
このコマンドには管理者権限が必要な場合があり、パスワードの入力を求められることがあります。
実行中の PHP: 8.3.10
ターゲット PHP インストール: 8.3.10 nts、Linux/OSX/etc x86_64（/usr/bin/php8.3 から）
パッケージが見つかりました: example/example-pie-extension:1.0.1（ext-example_pie_extension を提供）
phpize 完了。
設定完了。
ビルド完了: /tmp/pie_downloader_66e0b1de73cdb6.04069773/example-example-pie-extension-769f906/modules/example_pie_extension.so
インストール完了: /usr/lib/php/20230831/example_pie_extension.so
php.ini に "extension=example_pie_extension" を追加する必要があります
$
```

## PHP プロジェクトのすべての拡張機能をインストール

PHP プロジェクト内で、不足しているトップレベルの拡張機能をインストールできます：

```
$ pie install
🥧 PHP Installer for Extensions (PIE), 0.9.0, from The PHP Foundation
実行中の PHP: 8.3.19
ターゲット PHP インストール: 8.3.19 nts、Linux/OSX/etc x86_64（/usr/bin/php8.3 から）
プロジェクト your-vendor/your-project の拡張機能を確認中
requires: curl ✅ 既にインストール済み
requires: intl ✅ 既にインストール済み
requires: json ✅ 既にインストール済み
requires: example_pie_extension ⚠️  不足

以下のパッケージが適切です。どれをインストールしますか：
  [0] なし
  [1] asgrim/example-pie-extension: Example PIE extension
 > 1
   > 🥧 PHP Installer for Extensions (PIE), 0.9.0, from The PHP Foundation
   > このコマンドには管理者権限が必要な場合があり、パスワードの入力を求められることがあります。
   > 実行中の PHP: 8.3.19
   > ターゲット PHP インストール: 8.3.19 nts、Linux/OSX/etc x86_64（/usr/bin/php8.3 から）
   > パッケージが見つかりました: asgrim/example-pie-extension:2.0.2（ext-example_pie_extension を提供）
   ... (省略) ...
   > ✅ 拡張機能は /usr/bin/php8.3 で有効化され、読み込まれています

拡張機能の確認が完了しました。
```

## PIE をサポートする拡張機能

PIE をサポートする拡張機能のリストは
[https://packagist.org/extensions](https://packagist.org/extensions) で確認できます。

# バグ、機能のアイデア、質問、ヘルプが必要など

 - アイデア、質問、またはヘルプが必要な場合は、[ディスカッション](https://github.com/php/pie/discussions) を使用してください。
   - **新機能を貢献する前に、まずご連絡ください** - これは
     すでに開発中の可能性があります。PHP Foundation も積極的に
     これを開発しており、すでにパイプラインに新しい機能があります...
 - 報告するバグがある場合は、[Issues](https://github.com/php/pie/issues) を使用してください。

# 拡張機能のメンテナーです - PIE サポートを追加するにはどうすればよいですか？

拡張機能のメンテナーで、拡張機能に PIE サポートを追加したい場合は、
[拡張機能メンテナー](./docs/extension-maintainers.md) を読んでください。

## その他のドキュメント...

PIE の完全なドキュメントは [使用法](./docs/usage.md) ドキュメントにあります。

