---
title: PIE 维护者手册
order: 3
---
# PIE 维护者手册

## 分支策略

目前，我们使用单一的 `main` 分支和功能分支。未来，为了更好地支持补丁版本，我们可能会切换到版本化分支策略。

## 发布流程

确保您拥有要发布的最新版本，例如：

```shell
# 使用 git reset（注意：会丢弃 `main` 上的任何本地提交）
git checkout main && git fetch upstream && git reset --hard upstream/main
# 或者，使用 git pull（使用 `--ff-only` 避免创建合并提交）
git checkout main && git pull --ff-only upstream main
```

准备变更日志，设置要发布的版本和里程碑，例如：

```shell
PIE_VERSION=1.3.0
PIE_MILESTONE=$PIE_VERSION
```

> [!TIP]
> 对于预发布版本，您可以将版本/里程碑设置为不同的值，例如：
>
> ```shell
> PIE_VERSION=1.3.0-alpha.2
> PIE_MILESTONE=1.3.0
> ```
>
> 这将使用 `1.3.0-alpha.2` 版本进行标记/发布，但会基于 GitHub 中的 `1.3.0` 里程碑生成变更日志。

然后生成变更日志文件：

```shell
composer require --dev -W jwage/changelog-generator --no-interaction
vendor/bin/changelog-generator generate --user=php --repository=pie --milestone=$PIE_MILESTONE > CHANGELOG-$PIE_VERSION.md
git checkout -- composer.*
composer install
```

检查您是否对变更日志的内容满意。创建一个签名标签：

```shell
git tag -s $PIE_VERSION -F CHANGELOG-$PIE_VERSION.md
git push upstream $PIE_VERSION
```

发布流水线将运行，它将创建一个**草稿**发布版本，构建 PHAR 文件并附加它。然后，您必须转到 GitHub 上的草稿发布版本，验证一切正确，然后发布该发布版本。

```shell
rm CHANGELOG-$PIE_VERSION.md
```

