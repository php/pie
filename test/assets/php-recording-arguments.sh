#!/usr/bin/env bash

printf '%s\n' "${*//$'\n'/ }" >> "$PIE_TEST_PHP_ARGUMENTS_LOG"

exec "$PIE_TEST_REAL_PHP" "$@"
