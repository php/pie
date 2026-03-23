---
title: PIE Maintainers Handbook
order: 3
---
# PIE Maintainers Handbook

Thank you for taking an interest in contributing to PIE. This guide is written to help humans develop on PIE.

## Contributing to PIE

## Reporting bugs

If you think you have a bug, please [open an issue](https://github.com/php/pie/issues), and include:

 - What platform and PIE version you're using
 - A summary of:
   - What are you trying to do?
   - What do you expect to happen?
   - What is actually happening?
 - The steps to reproduce the issue - please use the `-v` verbose flag (or higher)
 - Complete error messages and/or logs, including stack traces (hint: use `-v` for verbose), but please redact sensitive information

## Submitting PRs

Please **do not** just submit PRs for features or ideas without discussing them first. **Start by
[creating a discussion](https://github.com/php/pie/discussions) first** if there is not already an open discussion or
issue. If there is already an open discussion or issue, please comment and wait for feedback before starting any work.
This is because the work may already be in progress or being investigated already. The PIE project is actively being
developed, and many features are already in discussion or being developed, so if you do not discuss with us first, you
may be duplicating work already in progress.

> [!TIP]
> We try to stay on top of issues already being worked on with the `maintainer investigating` purple label. If you see
> this on an issue, it is very likely we are already looking into this.

## Branching strategy

Since 1.3.0, we operate a branch per minor release, with an `x` for the patch
version, for example, 1.3 series branch is named `1.3.x`, 1.4 is named `1.4.x`
and so on. This allows releasing patch versions on older releases if a bug is
found, for example.

### New features

New feature branches should be based on the latest trunk (i.e. the default
branch). Once merged, that feature will be part of the next minor release.

### Bugfixes/Patches

Bugfixes/patches should be based on the oldest supported or desired patch
version. For example, if a bug affects the 1.3 series, a PR should be made
from the feature branch to the `1.3.x` branch.

## Developing on PIE

> [!IMPORTANT]
> Please read the above section BEFORE doing work on PIE; PIE is in very active development by the PHP Foundation, and
> many features/issues are already being worked on.

All the below tests are run in CI, on various versions of PHP, to ensure they do not get missed! However, when
developing on PIE, you will likely need to run some, or all, of these tests by hand.

> [!TIP]
> The development guide is primarily aimed at Linux systems, please adjust accordingly for your own platform.

### Unit & Integration Tests

> [!CAUTION]
> The integration test suite interacts directly with the PHP installation that is used to run the tests. This may
> result in the `asgrim/example-pie-extension` extension being added (sometimes in a broken state) to your PHP install.
> Do not run this using a PHP installation that you care about!

We use PHPUnit, which is installed with Composer, and can be run with:

```shell
sudo vendor/bin/phpunit
```

If you have multiple versions of PHP, you may add that in, e.g.:

```shell
sudo /usr/bin/php8.3 vendor/bin/phpunit
```

> [!TIP]
> `sudo` **is** required due to the way some of the tests interact with `sudo`

Coverage HTML report can be generated with:

```shell
sudo XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage
```

### Behaviour Testing

The primary features are documented in `features/*.feature`, in [Gherkin](https://cucumber.io/docs/gherkin/) format.

Some of these features are implemented as behaviour tests, using [Behat](https://behat.org/). However, these tests
should NOT be run on your machine directly. Instead, there is a predefined and controlled environment made with Docker.
This is done because many of the tests require specific dependencies, and this helps provide a repeatable test harness.

This can be run in two steps; first build the Docker container:

```shell
GITHUB_TOKEN=$(composer config --global --auth github-oauth.github.com) docker buildx build --file .github/pie-behaviour-tests/Dockerfile --secret id=GITHUB_TOKEN,env=GITHUB_TOKEN -t pie-behat-test .
```

Optionally, pass the `--build-arg PHP_VERSION=8.3` parameter to the above command to change the version of PHP used in
the test. Then you may run the tests with:

```shell
docker run --volume .:/github/workspace -ti pie-behat-test
```

### End-to-end Testing

We have a small number of end-to-end type tests, that are designed to ensure PIE runs in particular ways in different
systems. The test runner is a shell script that builds a Docker target, and runs it; and fails if a non-zero exit
occurs.

```shell
test/end-to-end/dockerfile-e2e-test.sh
```

This is particularly useful for testing different setups (e.g. Alpine, Ubuntu, Fedora, Brew, etc.) and ensuring build
tools and system dependencies are automatically installed.

### Static Analysis

We use [PHPStan](https://phpstan.org/) and several plugins to run static analysis on the codebase. You can run this:

```shell
vendor/bin/phpstan
```

### Coding Standards

We use [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer/) using the
[Doctrine Coding Standard](https://github.com/doctrine/coding-standard) to check and automatically fix CS issues.

To check the CS conformity:

```shell
vendor/bin/phpcs
```

To attempt to auto-apply CS fixes:

```shell
vendor/bin/phpcbf
```

### Mutation Testing

Install the `infection.phar` according to the [Infection PHP documentation](https://infection.github.io/guide/installation.html#Phar).

Tests can be run with:

```shell
sudo infection --min-msi=68 --min-covered-msi=68 --threads=max
```

> [!TIP]
> As with the tests, `sudo` is required due to the way some of the tests interact with `sudo`

### Documentation

Documentation can be generated with:

```shell
.github/docs/build-docs.sh
```

This is not normally needed, unless you are changing the template, as CI will generate the documentation and publish to
the GitHub Page.

## Release process

Make sure you have the latest version of the trunk to be released, for example,
one of:

```shell
# Using git reset (note: discards any local commits on `1.3.x`)
git checkout 1.3.x && git fetch upstream && git reset --hard upstream/1.3.x
# or, using git pull (note: use `--ff-only` to avoid making merge commits)
git checkout 1.3.x && git pull --ff-only upstream 1.3.x
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

### Minor or Major releases: updating branches

Once a minor or major release is made, a new trunk should be created. For
example, if you just released `1.3.0` from the `1.3.x` branch, you should then
create a new `1.4.x` branch, and set that as the default.
