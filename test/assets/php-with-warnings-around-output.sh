#!/usr/bin/env bash

echo "PHP Warning:  PHP Startup: Unable to load dynamic library 'redis' (tried: /path/to/redis (dlopen(/path/to/redis, 0x0009): [...] in Unknown on line 0"
echo "Deprecated: PHP Startup: session.sid_length INI setting is deprecated in Unknown on line 0"

"$PIE_TEST_REAL_PHP" "$@"
status=$?

echo ""
echo "Warning: Module \"foo\" is already loaded in Unknown on line 0"
echo "PHP Deprecated:  PHP Startup: session.sid_length INI setting is deprecated in Unknown on line 0"

exit $status
