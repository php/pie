#!/usr/bin/env bash

set -xeuo pipefail

cd "$(dirname "$0")/../.."

rm -Rf docs-package
docker buildx build -f .github/docs/Dockerfile --target=output --output=docs-package .
