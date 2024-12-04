---
title: Supported Extensions
order: 4
---
# Supported Extensions

Since Packagist is the new home for PIE packages, the full list of supported,
PIE-compatible extensions can be found on:

 * **[https://packagist.org/extensions](https://packagist.org/extensions)**

The process for adding support for PIE is documented in the
[Extension Maintainers](extension-maintainers.md) documentation.

## PECL Extension Migration

The PECL repository still has a whole host of extensions that have not yet
added support for PIE. This is a list of extensions hosted on PECL, and their
status for adding PIE support. If you spot some out of date information here,
please do submit a [Pull Request](https://github.com/php/pie/pulls).

| PECL Extension       | Status                                                                                                                                             |
|----------------------|----------------------------------------------------------------------------------------------------------------------------------------------------|
| imagick              | ⏰ PR: [Imagick/imagick#688](https://github.com/Imagick/imagick/pull/688)                                                                           |
| Xdebug               | ⏰ Coming soon in 3.4.0: [xdebug/xdebug](https://packagist.org/packages/xdebug/xdebug)                                                              |
| redis                | ✅ Supported: [phpredis/phpredis](https://packagist.org/packages/phpredis/phpredis)                                                                 |
| APCu                 | ✅ Supported: [apcu/apcu](https://packagist.org/packages/apcu/apcu)                                                                                 |
| yaml                 | ⏰ PR: [php/pecl-file_formats-yaml#88](https://github.com/php/pecl-file_formats-yaml/pull/88)                                                       |
| htscanner            | ❌ Abandoned                                                                                                                                        |
| memcached            | ⏰ PR [php-memcached-dev/php-memcached#560](https://github.com/php-memcached-dev/php-memcached/pull/560) was merged, but not yet added to Packagist |
| mongodb              | ✅ Supported: [mongodb/mongodb-extension](https://packagist.org/packages/mongodb/mongodb-extension)                                                 |
| timezonedb           | ⏰ PR: [php/pecl-datetime-timezonedb#12](https://github.com/php/pecl-datetime-timezonedb/pull/12)                                                   |
| pcov                 | ✅ Supported: [pecl/pcov](https://packagist.org/packages/pecl/pcov)                                                                                 |
| mcrypt               | ⏰ PR: [php/pecl-encryption-mcrypt#20](https://github.com/php/pecl-encryption-mcrypt/pull/20)                                                       |
| amqp                 | ⏰ PR: [php-amqp/php-amqp#584](https://github.com/php-amqp/php-amqp/pull/584)                                                                       |
| memcache             | ⏰ PR: [websupport-sk/pecl-memcache#116](https://github.com/websupport-sk/pecl-memcache/pull/116)                                                   |
| zip                  | ?                                                                                                                                                  |
| igbinary             | ?                                                                                                                                                  |
| ssh2                 | ?                                                                                                                                                  |
| swoole               | ?                                                                                                                                                  |
| mongo                | ❌ Abandoned                                                                                                                                        |
| APC                  | ❌ Abandoned                                                                                                                                        |
| sqlsrv               | ?                                                                                                                                                  |
| rdkafka              | ?                                                                                                                                                  |
| pdo_sqlsrv           | ?                                                                                                                                                  |
| mailparse            | ?                                                                                                                                                  |
| oci8                 | ?                                                                                                                                                  |
| gnupg                | ?                                                                                                                                                  |
| pecl_http            | ?                                                                                                                                                  |
| msgpack              | ?                                                                                                                                                  |
| geoip                | ?                                                                                                                                                  |
| gRPC                 | ?                                                                                                                                                  |
| oauth                | ?                                                                                                                                                  |
| ast                  | ?                                                                                                                                                  |
| uploadprogress       | ?                                                                                                                                                  |
| libsodium            | ?                                                                                                                                                  |
| xhprof               | ?                                                                                                                                                  |
| Mosquitto            | ?                                                                                                                                                  |
| couchbase            | ?                                                                                                                                                  |
| apcu_bc              | ?                                                                                                                                                  |
| intl                 | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| protobuf             | ?                                                                                                                                                  |
| smbclient            | ?                                                                                                                                                  |
| PDO                  | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| event                | ?                                                                                                                                                  |
| raphf                | ?                                                                                                                                                  |
| gearman              | ?                                                                                                                                                  |
| pdflib               | ❌ Abandoned                                                                                                                                        |
| lzf                  | ?                                                                                                                                                  |
| mogilefs             | ?                                                                                                                                                  |
| uuid                 | ✅ Supported: [pecl/uuid](https://packagist.org/packages/pecl/uuid)                                                                                |
| uopz                 | ?                                                                                                                                                  |
| PDO_MYSQL            | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| ev                   | ?                                                                                                                                                  |
| propro               | ?                                                                                                                                                  |
| ds                   | ?                                                                                                                                                  |
| hprose               | ?                                                                                                                                                  |
| Fileinfo             | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| stomp                | ?                                                                                                                                                  |
| solr                 | ?                                                                                                                                                  |
| excimer              | ?                                                                                                                                                  |
| json                 | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| gmagick              | ?                                                                                                                                                  |
| decimal              | ?                                                                                                                                                  |
| stats                | ❌ Abandoned                                                                                                                                        |
| opentelemetry        | ?                                                                                                                                                  |
| ZendOptimizerPlus    | ❌ Possibly abandoned - [gives a 404 on PECL](https://pecl.php.net/package/ZendOptimizerPlus)                                                       |
| rar                  | ?                                                                                                                                                  |
| psr                  | ?                                                                                                                                                  |
| xmlrpc               | ❌ Abandoned                                                                                                                                        |
| opencensus           | ?                                                                                                                                                  |
| pthreads             | ❌ Abandoned                                                                                                                                        |
| xlswriter            | ?                                                                                                                                                  |
| inotify              | ?                                                                                                                                                  |
| zmq                  | ❌ Abandoned                                                                                                                                        |
| Yaf                  | ?                                                                                                                                                  |
| cassandra            | ?                                                                                                                                                  |
| dbase                | ?                                                                                                                                                  |
| datadog_trace        | ?                                                                                                                                                  |
| v8js                 | ?                                                                                                                                                  |
| svn                  | ?                                                                                                                                                  |
| vips                 | ?                                                                                                                                                  |
| ibm_db2              | ?                                                                                                                                                  |
| timecop              | ?                                                                                                                                                  |
| openswoole           | ?                                                                                                                                                  |
| radius               | ?                                                                                                                                                  |
| zstd                 | ?                                                                                                                                                  |
| xdiff                | ?                                                                                                                                                  |
| tidy                 | ❌ Abandoned                                                                                                                                        |
| trader               | ?                                                                                                                                                  |
| sphinx               | ❌ Abandoned                                                                                                                                        |
| Phalcon              | ?                                                                                                                                                  |
| runkit7              | ?                                                                                                                                                  |
| runkit               | ❌ Abandoned                                                                                                                                        |
| yaz                  | ?                                                                                                                                                  |
| libevent             | ❌ Abandoned                                                                                                                                        |
| SQLite               | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| zookeeper            | ?                                                                                                                                                  |
| phar                 | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| SeasLog              | ?                                                                                                                                                  |
| dio                  | ✅ Supported: [pecl/dio](https://packagist.org/packages/pecl/dio)                                                                                  |
| apfd                 | ?                                                                                                                                                  |
| apd                  | ❌ Abandoned                                                                                                                                        |
| pcs                  | ?                                                                                                                                                  |
| hash                 | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| PDO_PGSQL            | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| jsmin                | ?                                                                                                                                                  |
| eio                  | ?                                                                                                                                                  |
| gender               | ?                                                                                                                                                  |
| maxminddb            | ?                                                                                                                                                  |
| bcompiler            | ❌ Abandoned                                                                                                                                        |
| PAM                  | ?                                                                                                                                                  |
| parallel             | ?                                                                                                                                                  |
| krb5                 | ?                                                                                                                                                  |
| yar                  | ?                                                                                                                                                  |
| rrd                  | ?                                                                                                                                                  |
| filter               | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| vld                  | ?                                                                                                                                                  |
| PDO_OCI              | ?                                                                                                                                                  |
| lua                  | ?                                                                                                                                                  |
| taint                | ?                                                                                                                                                  |
| PDO_SQLITE           | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| scrypt               | ?                                                                                                                                                  |
| Bitset               | ?                                                                                                                                                  |
| ssdeep               | ?                                                                                                                                                  |
| jsonc                | ?                                                                                                                                                  |
| bz2                  | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| cairo                | ❌ Abandoned                                                                                                                                        |
| uri_template         | ❌ Abandoned                                                                                                                                        |
| mysqlnd_ms           | ❌ Abandoned                                                                                                                                        |
| crypto               | ?                                                                                                                                                  |
| WinCache             | ?                                                                                                                                                  |
| memprof              | ?                                                                                                                                                  |
| sync                 | ?                                                                                                                                                  |
| bbcode               | ❌ Abandoned                                                                                                                                        |
| SPL_Types            | ❌ Abandoned                                                                                                                                        |
| proctitle            | ❌ Abandoned                                                                                                                                        |
| Weakref              | ❌ Abandoned                                                                                                                                        |
| scoutapm             | ?                                                                                                                                                  |
| mysqlnd_azure        | ?                                                                                                                                                  |
| svm                  | ?                                                                                                                                                  |
| xmldiff              | ?                                                                                                                                                  |
| perl                 | ❌ Abandoned                                                                                                                                        |
| expect               | ?                                                                                                                                                  |
| PDO_IDS              | ❌ Possibly abandoned - [gives a 404 on PECL](https://pecl.php.net/package/PDO_IDS)                                                                 |
| json_post            | ?                                                                                                                                                  |
| yac                  | ?                                                                                                                                                  |
| xattr                | ?                                                                                                                                                  |
| PDO_DBLIB            | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| fribidi              | ❌ Abandoned                                                                                                                                        |
| ps                   | ?                                                                                                                                                  |
| xmlwriter            | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| uv                   | ?                                                                                                                                                  |
| mono                 | ❌ Abandoned                                                                                                                                        |
| zephir_parser        | ?                                                                                                                                                  |
| pq                   | ?                                                                                                                                                  |
| ncurses              | ❌ Abandoned                                                                                                                                        |
| fann                 | ?                                                                                                                                                  |
| sdo                  | ❌ Possibly abandoned - [gives a 404 on PECL](https://pecl.php.net/package/sdo)                                                                     |
| varnish              | ?                                                                                                                                                  |
| parle                | ?                                                                                                                                                  |
| CSV                  | ?                                                                                                                                                  |
| id3                  | ❌ Abandoned                                                                                                                                        |
| xmlReader            | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| geospatial           | ?                                                                                                                                                  |
| PDO_ODBC             | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| crack                | ❌ Abandoned                                                                                                                                        |
| mustache             | ?                                                                                                                                                  |
| sqlite3              | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| big_int              | ❌ Abandoned                                                                                                                                        |
| Paradox              | ❌ Abandoned                                                                                                                                        |
| haru                 | ❌ Abandoned                                                                                                                                        |
| LuaSandbox           | ?                                                                                                                                                  |
| sundown              | ❌ Abandoned                                                                                                                                        |
| mysql                | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| APM                  | ?                                                                                                                                                  |
| stem                 | ?                                                                                                                                                  |
| idn                  | ?                                                                                                                                                  |
| enchant              | ❌ Abandoned                                                                                                                                        |
| ui                   | ?                                                                                                                                                  |
| POP3                 | ❌ Abandoned                                                                                                                                        |
| PDO_IBM              | ?                                                                                                                                                  |
| doublemetaphone      | ?                                                                                                                                                  |
| hrtime               | ?                                                                                                                                                  |
| newt                 | ❌ Abandoned                                                                                                                                        |
| simdjson             | ?                                                                                                                                                  |
| yaconf               | ?                                                                                                                                                  |
| translit             | ?                                                                                                                                                  |
| cybermut             | ?                                                                                                                                                  |
| xxtea                | ?                                                                                                                                                  |
| tcpwrap              | ?                                                                                                                                                  |
| stackdriver_debugger | ?                                                                                                                                                  |
| syck                 | ?                                                                                                                                                  |
| PDO_CUBRID           | ?                                                                                                                                                  |
| dtrace               | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| cyrus                | ❌ Abandoned                                                                                                                                        |
| PDO_SQLANYWHERE      | ?                                                                                                                                                  |
| TCLink               | ?                                                                                                                                                  |
| spplus               | ?                                                                                                                                                  |
| jsonpath             | ?                                                                                                                                                  |
| vpopmail             | ❌ Abandoned                                                                                                                                        |
| dbx                  | ❌ Abandoned                                                                                                                                        |
| kadm5                | ❌ Abandoned                                                                                                                                        |
| parsekit             | ❌ Abandoned                                                                                                                                        |
| cmark                | ?                                                                                                                                                  |
| odbtp                | ❌ Abandoned                                                                                                                                        |
| wbxml                | ❌ Abandoned                                                                                                                                        |
| ahocorasick          | ?                                                                                                                                                  |
| WinBinder            | ?                                                                                                                                                  |
| tokyo_tyrant         | ❌ Abandoned                                                                                                                                        |
| mcve                 | ❌ Abandoned                                                                                                                                        |
| SeasClick            | ?                                                                                                                                                  |
| brotli               | ?                                                                                                                                                  |
| CUBRID               | ?                                                                                                                                                  |
| operator             | ❌ Abandoned                                                                                                                                        |
| Judy                 | ❌ Abandoned                                                                                                                                        |
| AOP                  | ?                                                                                                                                                  |
| mdbtools             | ❌ Abandoned                                                                                                                                        |
| mysql_xdevapi        | ?                                                                                                                                                  |
| html_parse           | ❌ Abandoned                                                                                                                                        |
| maxdb                | ❌ Abandoned                                                                                                                                        |
| sasl                 | ?                                                                                                                                                  |
| riak                 | ❌ Abandoned                                                                                                                                        |
| XMLRPCi              | ?                                                                                                                                                  |
| jsonnet              | ?                                                                                                                                                  |
| huffman              | ❌ Abandoned                                                                                                                                        |
| mqseries             | ?                                                                                                                                                  |
| componere            | ?                                                                                                                                                  |
| GDChart              | ❌ Abandoned                                                                                                                                        |
| mnogosearch          | ❌ Abandoned                                                                                                                                        |
| PKCS11               | ?                                                                                                                                                  |
| protocolbuffers      | ❌ Abandoned                                                                                                                                        |
| hidef                | ❌ Abandoned                                                                                                                                        |
| ingres               | ❌ Abandoned                                                                                                                                        |
| BLENC                | ❌ Abandoned                                                                                                                                        |
| PECL_Gen             | ❌ Abandoned                                                                                                                                        |
| zlib_filter          | ?                                                                                                                                                  |
| amfext               | ❌ Abandoned                                                                                                                                        |
| dazuko               | ?                                                                                                                                                  |
| python               | ❌ Abandoned                                                                                                                                        |
| awscrt               | ?                                                                                                                                                  |
| spidermonkey         | ❌ Abandoned                                                                                                                                        |
| Tensor               | ?                                                                                                                                                  |
| jsond                | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| classkit             | ❌ Abandoned                                                                                                                                        |
| statgrab             | ?                                                                                                                                                  |
| nsq                  | ?                                                                                                                                                  |
| inclued              | ❌ Abandoned                                                                                                                                        |
| swish                | ❌ Abandoned                                                                                                                                        |
| chdb                 | ❌ Abandoned                                                                                                                                        |
| env                  | ?                                                                                                                                                  |
| sam                  | ❌ Abandoned                                                                                                                                        |
| Druid                | ?                                                                                                                                                  |
| bz2_filter           | ?                                                                                                                                                  |
| Net_Gopher           | ❌ Abandoned                                                                                                                                        |
| ffi                  | ❌ Abandoned                                                                                                                                        |
| DBus                 | ❌ Abandoned                                                                                                                                        |
| xslcache             | ❌ Abandoned                                                                                                                                        |
| leveldb              | ?                                                                                                                                                  |
| mcrypt_filter        | ?                                                                                                                                                  |
| ecasound             | ?                                                                                                                                                  |
| win32std             | ❌ Abandoned                                                                                                                                        |
| simple_kafka_client  | ?                                                                                                                                                  |
| win32service         | ?                                                                                                                                                  |
| mysqlnd_qc           | ❌ Abandoned                                                                                                                                        |
| php_trie             | ?                                                                                                                                                  |
| spread               | ?                                                                                                                                                  |
| ion                  | ?                                                                                                                                                  |
| crack_dll            | ?                                                                                                                                                  |
| ip2location          | ?                                                                                                                                                  |
| zeroconf             | ?                                                                                                                                                  |
| graphdat             | ?                                                                                                                                                  |
| date_time            | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| colorer              | ❌ Abandoned                                                                                                                                        |
| pcsc                 | ?                                                                                                                                                  |
| shape                | ?                                                                                                                                                  |
| perforce             | ?                                                                                                                                                  |
| mysqlnd_memcache     | ❌ Abandoned                                                                                                                                        |
| PHK                  | ❌ Abandoned                                                                                                                                        |
| lchash               | ❌ Abandoned                                                                                                                                        |
| PDO_FIREBIRD         | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| win32ps              | ❌ Abandoned                                                                                                                                        |
| FreeImage            | ❌ Abandoned                                                                                                                                        |
| dom_varimport        | ❌ Abandoned                                                                                                                                        |
| qqwry                | ?                                                                                                                                                  |
| sdl                  | ?                                                                                                                                                  |
| txforward            | ❌ Abandoned                                                                                                                                        |
| hdr_histogram        | ⏰ Coming soon: [beberlei/hdrhistogram](https://packagist.org/packages/beberlei/hdrhistogram)                                                       |
| cybercash            | ❌ Abandoned                                                                                                                                        |
| Valkyrie             | ❌ Abandoned                                                                                                                                        |
| skywalking           | ?                                                                                                                                                  |
| xrange               | ❌ Abandoned                                                                                                                                        |
| augeas               | ?                                                                                                                                                  |
| gupnp                | ❌ Abandoned                                                                                                                                        |
| win32ps_dll          | ❌ Abandoned                                                                                                                                        |
| docblock             | ?                                                                                                                                                  |
| termbox              | ?                                                                                                                                                  |
| selinux              | ✅ Supported: [pecl/selinux](https://packagist.org/packages/pecl/selinux)                                                                          |
| imap                 | ?                                                                                                                                                  |
| KTaglib              | ❌ Abandoned                                                                                                                                        |
| funcall              | ❌ Abandoned                                                                                                                                        |
| params               | ?                                                                                                                                                  |
| Parse_Tree           | ❌ Abandoned                                                                                                                                        |
| Molten               | ?                                                                                                                                                  |
| clips                | ?                                                                                                                                                  |
| trace                | ?                                                                                                                                                  |
| apn                  | ❌ Abandoned                                                                                                                                        |
| cld                  | ?                                                                                                                                                  |
| cvsclient            | ?                                                                                                                                                  |
| clucene              | ?                                                                                                                                                  |
| panda                | ❌ Abandoned                                                                                                                                        |
| wxwidgets            | ❌ Abandoned                                                                                                                                        |
| tk                   | ❌ Abandoned                                                                                                                                        |
| v8                   | ?                                                                                                                                                  |
| rpminfo              | ✅ Supported: [remi/rpminfo](https://packagist.org/packages/remi/rpminfo)                                                                          |
| skywalking_agent     | ?                                                                                                                                                  |
| markdown             | ?                                                                                                                                                  |
| ares                 | ❌ Abandoned                                                                                                                                        |
| scream               | ❌ Abandoned                                                                                                                                        |
| netools              | ❌ Abandoned                                                                                                                                        |
| drizzle              | ❌ Abandoned                                                                                                                                        |
| coin_acceptor        | ❌ Abandoned                                                                                                                                        |
| xcommerce            | ❌ Abandoned                                                                                                                                        |
| courierauth          | ?                                                                                                                                                  |
| ApacheAccessor       | ?                                                                                                                                                  |
| rsync                | ❌ Abandoned                                                                                                                                        |
| fuse                 | ❌ Abandoned                                                                                                                                        |
| rsvg                 | ❌ Abandoned                                                                                                                                        |
| esmtp                | ?                                                                                                                                                  |
| archive              | ❌ Abandoned                                                                                                                                        |
| qb                   | ❌ Abandoned                                                                                                                                        |
| quickhash            | ?                                                                                                                                                  |
| cairo_wrapper        | ?                                                                                                                                                  |
| intercept            | ?                                                                                                                                                  |
| imlib2               | ❌ Abandoned                                                                                                                                        |
| bloomy               | ?                                                                                                                                                  |
| pdo_user             | ❌ Abandoned                                                                                                                                        |
| tdb                  | ?                                                                                                                                                  |
| PDO_4D               | ❌ Abandoned                                                                                                                                        |
| rpmreader            | ❌ Abandoned                                                                                                                                        |
| memtrack             | ❌ Abandoned                                                                                                                                        |
| tvision              | ?                                                                                                                                                  |
| strict               | ❌ Abandoned                                                                                                                                        |
| yp                   | ?                                                                                                                                                  |
| openal               | ?                                                                                                                                                  |
| yami                 | ❌ Abandoned                                                                                                                                        |
| oggvorbis            | ❌ Abandoned                                                                                                                                        |
| memsession           | ❌ Abandoned                                                                                                                                        |
| binpack              | ❌ Abandoned                                                                                                                                        |
| DBDO                 | ❌ Abandoned                                                                                                                                        |
| handlebars           | ?                                                                                                                                                  |
| phdfs                | ❌ Abandoned                                                                                                                                        |
| request              | ?                                                                                                                                                  |
| isis                 | ?                                                                                                                                                  |
| fam                  | ❌ Abandoned                                                                                                                                        |
| xpass                | ✅ Supported: [remi/xpass](https://packagist.org/packages/remi/xpass)                                                                              |
| ocal                 | ?                                                                                                                                                  |
| lapack               | ❌ Abandoned                                                                                                                                        |
| opendirectory        | ❌ Abandoned                                                                                                                                        |
| xmms                 | ❌ Abandoned                                                                                                                                        |
| dbplus               | ❌ Abandoned                                                                                                                                        |
| framegrab            | ?                                                                                                                                                  |
| base58               | ?                                                                                                                                                  |
| IMS                  | ?                                                                                                                                                  |
| TextCat              | ?                                                                                                                                                  |
| functional           | ?                                                                                                                                                  |
| pledge               | ?                                                                                                                                                  |
| ref                  | ?                                                                                                                                                  |
| opengl               | ?                                                                                                                                                  |
| ecma_intl            | ?                                                                                                                                                  |
| var_representation   | ?                                                                                                                                                  |
| tcc                  | ?                                                                                                                                                  |
| automap              | ❌ Abandoned                                                                                                                                        |
| xmp                  | ❌ Abandoned                                                                                                                                        |
| optimizer            | ❌ Abandoned                                                                                                                                        |
| phpy                 | ?                                                                                                                                                  |
| udis86               | ❌ Abandoned                                                                                                                                        |
| http_message         | ?                                                                                                                                                  |
| memoize              | ?                                                                                                                                                  |
| re2                  | ❌ Abandoned                                                                                                                                        |
| ice                  | ?                                                                                                                                                  |
| teds                 | ?                                                                                                                                                  |
| mysqlnd_uh           | ❌ Abandoned                                                                                                                                        |
| sandbox              | ?                                                                                                                                                  |
| ircclient            | ❌ Abandoned                                                                                                                                        |
| swoole_serialize     | ?                                                                                                                                                  |
| pspell               | ?                                                                                                                                                  |
| wasm                 | ?                                                                                                                                                  |
| meta                 | ?                                                                                                                                                  |
| ip2proxy             | ?                                                                                                                                                  |
| bsdiff               | ?                                                                                                                                                  |
| sdl_mixer            | ?                                                                                                                                                  |
| axis2                | ❌ Abandoned                                                                                                                                        |
| SeasSnowflake        | ?                                                                                                                                                  |
| rnp                  | ?                                                                                                                                                  |
| PDO_TAOS             | ?                                                                                                                                                  |
| namazu               | ?                                                                                                                                                  |
| weakreference_bc     | ?                                                                                                                                                  |
| sdl_ttf              | ?                                                                                                                                                  |
| sdl_image            | ?                                                                                                                                                  |
| pinpoint_php         | ?                                                                                                                                                  |
| orng                 | ❌ Abandoned                                                                                                                                        |
| immutable_cache      | ?                                                                                                                                                  |
| mysqlnd_krb          | ?                                                                                                                                                  |
| mysqlnd_ngen         | ❌ Abandoned                                                                                                                                        |
