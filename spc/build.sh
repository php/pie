#!/usr/bin/env bash

set -xeuo pipefail

SPC_ROOT=$(dirname "$0")

# First up, build the regular pie.phar
cd "$SPC_ROOT/.."
php box.phar compile
mv pie.phar "$SPC_ROOT/pie.phar"

cd $SPC_ROOT

# Build the static PHP micro.sfx
./spc craft

# Combine pie.phar with the micro.sfx
./spc micro:combine pie.phar --output=pie.elf

# Docker build & run the test with it
docker build --file spc.Dockerfile --tag pie-spc-test .
docker run --rm -ti pie-spc-test
