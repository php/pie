#!/usr/bin/env bash

FOUND=0
for arg in "$@"; do
    if [ "$arg" == "--repo=php/pie" ] || [ "$arg" == "--help" ]; then
        FOUND=1
        break
    fi
done

if [ $FOUND -eq 0 ]; then
    echo "Error: --repo=php/pie parameter missing (and --help not found)" >&2
    exit 1
fi

echo "Pretending to be gh cli - happy path"
exit 0
