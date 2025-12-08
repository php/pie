---
title: PIE メンテナーハンドブック
order: 3
---
# PIE メンテナーハンドブック

## ブランチ戦略

現在、単一の `main` ブランチと機能ブランチを運用しています。将来、パッチバージョンをより適切にサポートするために、バージョン管理されたブランチ戦略に切り替える可能性があります。

## リリースプロセス

リリースする最新バージョンを持っていることを確認してください。例：

```shell
# git reset を使用（注意：`main` 上のローカルコミットは破棄されます）
git checkout main && git fetch upstream && git reset --hard upstream/main
# または、git pull を使用（マージコミットを避けるために `--ff-only` を使用）
git checkout main && git pull --ff-only upstream main
```

変更履歴を準備し、リリースするバージョンとマイルストーンを設定します。例：

```shell
PIE_VERSION=1.3.0
PIE_MILESTONE=$PIE_VERSION
```

> [!TIP]
> プレリリースの場合、バージョン/マイルストーンを異なる値に設定できます。例：
>
> ```shell
> PIE_VERSION=1.3.0-alpha.2
> PIE_MILESTONE=1.3.0
> ```
>
> これにより、`1.3.0-alpha.2` バージョンでタグ付け/リリースされますが、変更履歴は GitHub の `1.3.0` マイルストーンに基づいて生成されます。

次に、変更履歴ファイルを生成します：

```shell
composer require --dev -W jwage/changelog-generator --no-interaction
vendor/bin/changelog-generator generate --user=php --repository=pie --milestone=$PIE_MILESTONE > CHANGELOG-$PIE_VERSION.md
git checkout -- composer.*
composer install
```

変更履歴の内容に満足しているか確認してください。署名付きタグを作成します：

```shell
git tag -s $PIE_VERSION -F CHANGELOG-$PIE_VERSION.md
git push upstream $PIE_VERSION
```

リリースパイプラインが実行され、**ドラフト**リリースが作成され、PHAR ファイルがビルドされて添付されます。その後、GitHub のドラフトリリースに移動し、すべてが正しいことを確認してから、リリースを公開する必要があります。

```shell
rm CHANGELOG-$PIE_VERSION.md
```

