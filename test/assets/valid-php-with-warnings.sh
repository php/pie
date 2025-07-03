#!/usr/bin/env bash

# These should be implicitly filtered out by PIE
echo "PHP Warning:  PHP Startup: Unable to load dynamic library 'redis' (tried: /path/to/redis (dlopen(/path/to/redis, 0x0009): [...] in Unknown on line 0"
echo "Warning: PHP Startup: Unable to load dynamic library 'pdo_mysql' (tried: /path/to/pdo_mysql (/path/to/pdo_mysql: cannot open shared object file: No such file or directory), /path/to/pdo_mysql.so (/path/to/pdo_mysql.so: undefined symbol: mysqlnd_debug_std_no_trace_funcs)) in Unknown on line 0"
echo "Deprecated: Function unsafe_function() is deprecated since 1.5, use safe_replacement() instead in example.php on line 9"

# This is the expected output of PIE:
echo "PHP";
