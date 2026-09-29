#!/usr/bin/env bash

if [ "$1" = '--configure-options' ]; then
    echo "$PIE_TEST_FAKE_CONFIGURE_OPTIONS"
else
    exec "$PIE_TEST_REAL_PHP_CONFIG" "$@"
fi
