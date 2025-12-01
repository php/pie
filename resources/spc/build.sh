#!/usr/bin/env bash

# A little test build script for manually building a self-contained PIE executable
# Note; needs `spc` pre-installed:
# curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-x86_64
# chmod +x spc

set -xeuo pipefail

SPC_ROOT=$(dirname "$0")
PIE_PROJECT_ROOT="$SPC_ROOT/../.."

# First up, build the regular pie.phar
cd "$PIE_PROJECT_ROOT"
php box.phar compile
mv pie.phar "$SPC_ROOT/pie.phar"

cd "$SPC_ROOT"

# Build the static PHP micro.sfx
./spc craft

# Combine pie.phar with the micro.sfx
./spc micro:combine pie.phar --output=pie.elf

# Docker build & run the test with it
docker build --file spc.Dockerfile --tag pie-spc-test .
docker run --rm -ti pie-spc-test
