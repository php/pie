#!/usr/bin/env bash

if [[ "$*" == *"--help"* ]]; then
    exit 0
fi

echo "Pretending to be gh cli - unhappy path"
exit 1
