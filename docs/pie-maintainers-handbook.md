---
title: PIE Maintainers Handbook
order: 3
---
# PIE Maintainers Handbook

## Branching strategy

At the moment, we operate a single `main` branch, and feature branches. In the
future, to better facilitate patch versions, we may switch to a versioned
branching strategy.

## Release process

Make sure you have the latest version to be released, for example, one of:

```shell
# Using git reset (note: discards any local commits on `main`)
git checkout main && git fetch upstream && git reset --hard upstream/main
# or, using git pull (use `--ff-only` to avoid making merge commits)
git checkout main && git pull --ff-only upstream main
```

Prepare a changelog, set the version and milestone to be released, e.g.:

```shell
PIE_VERSION=1.3.0
PIE_MILESTONE=$PIE_VERSION
```

> [!TIP]
> For pre-releases, you can set the version/milestone to be different, e.g.:
>
> ```shell
> PIE_VERSION=1.3.0-alpha.2
> PIE_MILESTONE=1.3.0
> ```
>
> This will tag/release with the `1.3.0-alpha.2` version, but will generate the
> changelog based on the `1.3.0` milestone in GitHub.

Then generate the changelog file:

```shell
composer require --dev -W jwage/changelog-generator --no-interaction
vendor/bin/changelog-generator generate --user=php --repository=pie --milestone=$PIE_MILESTONE > CHANGELOG-$PIE_VERSION.md
git checkout -- composer.*
composer install
```

Check you are happy with the contents of the changelog. Create a signed tag:

```shell
git tag -s $PIE_VERSION -F CHANGELOG-$PIE_VERSION.md
git push upstream $PIE_VERSION
```

The release pipeline will run, which will create a **draft** release, build the
PHAR file, and attach it. You must then go to the draft release on GitHub,
verify everything is correct, and publish the release.

```shell
rm CHANGELOG-$PIE_VERSION.md
```
