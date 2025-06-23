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
| ahocorasick          | ?                                                                                                                                                  |
| amfext               | ❌ Abandoned                                                                                                                                        |
| amqp                 | ⏰ PR: [php-amqp/php-amqp#584](https://github.com/php-amqp/php-amqp/pull/584)                                                                       |
| AOP                  | ?                                                                                                                                                  |
| ApacheAccessor       | ?                                                                                                                                                  |
| APC                  | ❌ Abandoned                                                                                                                                        |
| APCu                 | ✅ Supported: [apcu/apcu](https://packagist.org/packages/apcu/apcu)                                                                                 |
| apcu_bc              | ?                                                                                                                                                  |
| apd                  | ❌ Abandoned                                                                                                                                        |
| apfd                 | ✅ Supported: [m6w6/ext-apfd](https://packagist.org/packages/m6w6/ext-apfd)                                                                         |
| APM                  | ?                                                                                                                                                  |
| apn                  | ❌ Abandoned                                                                                                                                        |
| archive              | ❌ Abandoned                                                                                                                                        |
| ares                 | ❌ Abandoned                                                                                                                                        |
| ast                  | ✅ Supported: [nikic/php-ast](https://packagist.org/packages/nikic/php-ast)                                                                         |
| augeas               | ?                                                                                                                                                  |
| automap              | ❌ Abandoned                                                                                                                                        |
| awscrt               | ?                                                                                                                                                  |
| axis2                | ❌ Abandoned                                                                                                                                        |
| base58               | ⏰ PR: [jasny/base58-php-ext#14](https://github.com/jasny/base58-php-ext/pull/14)                                                                   |
| bbcode               | ❌ Abandoned                                                                                                                                        |
| bcompiler            | ❌ Abandoned                                                                                                                                        |
| big_int              | ❌ Abandoned                                                                                                                                        |
| binpack              | ❌ Abandoned                                                                                                                                        |
| Bitset               | ⏰ PR: [php/pecl-numbers-bitset#16](https://github.com/php/pecl-numbers-bitset/pull/16)                                                             |
| BLENC                | ❌ Abandoned                                                                                                                                        |
| bloomy               | ?                                                                                                                                                  |
| brotli               | ✅ Supported: [kjdev/brotli](https://packagist.org/packages/kjdev/brotli)                                                                           |
| bsdiff               | ?                                                                                                                                                  |
| bz2                  | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| bz2_filter           | ?                                                                                                                                                  |
| cairo                | ❌ Abandoned                                                                                                                                        |
| cairo_wrapper        | ?                                                                                                                                                  |
| cassandra            | ?                                                                                                                                                  |
| chdb                 | ❌ Abandoned                                                                                                                                        |
| classkit             | ❌ Abandoned                                                                                                                                        |
| cld                  | ?                                                                                                                                                  |
| clips                | ?                                                                                                                                                  |
| clucene              | ?                                                                                                                                                  |
| cmark                | ?                                                                                                                                                  |
| coin_acceptor        | ❌ Abandoned                                                                                                                                        |
| colorer              | ❌ Abandoned                                                                                                                                        |
| componere            | ?                                                                                                                                                  |
| couchbase            | ?                                                                                                                                                  |
| courierauth          | ?                                                                                                                                                  |
| crack                | ❌ Abandoned                                                                                                                                        |
| crack_dll            | ?                                                                                                                                                  |
| crypto               | ⏰ PR: [bukka/php-crypto#43](https://github.com/bukka/php-crypto/pull/43)                                                                           |
| CSV                  | ✅ Supported: [girgias/csv](https://packagist.org/packages/girgias/csv)                                                                             |
| CUBRID               | ?                                                                                                                                                  |
| cvsclient            | ?                                                                                                                                                  |
| cybercash            | ❌ Abandoned                                                                                                                                        |
| cybermut             | ?                                                                                                                                                  |
| cyrus                | ❌ Abandoned                                                                                                                                        |
| datadog_trace        | ?                                                                                                                                                  |
| date_time            | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| dazuko               | ?                                                                                                                                                  |
| dbase                | ⏰ PR: [php/pecl-database-dbase#6](https://github.com/php/pecl-database-dbase/pull/6)                                                               |
| DBDO                 | ❌ Abandoned                                                                                                                                        |
| dbplus               | ❌ Abandoned                                                                                                                                        |
| DBus                 | ❌ Abandoned                                                                                                                                        |
| dbx                  | ❌ Abandoned                                                                                                                                        |
| decimal              | ⏰ PR: [php-decimal/ext-decimal#87](https://github.com/php-decimal/ext-decimal/pull/87)                                                             |
| dio                  | ✅ Supported: [pecl/dio](https://packagist.org/packages/pecl/dio)                                                                                   |
| docblock             | ?                                                                                                                                                  |
| dom_varimport        | ❌ Abandoned                                                                                                                                        |
| doublemetaphone      | ?                                                                                                                                                  |
| drizzle              | ❌ Abandoned                                                                                                                                        |
| Druid                | ?                                                                                                                                                  |
| ds                   | ⏰ PR: [php-ds/ext-ds#214](https://github.com/php-ds/ext-ds/pull/214)                                                                               |
| dtrace               | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| ecasound             | ?                                                                                                                                                  |
| ecma_intl            | ?                                                                                                                                                  |
| eio                  | ?                                                                                                                                                  |
| enchant              | ❌ Abandoned                                                                                                                                        |
| env                  | ?                                                                                                                                                  |
| esmtp                | ?                                                                                                                                                  |
| ev                   | ⏰ PR: [osmanov/pecl-ev#9](https://bitbucket.org/osmanov/pecl-ev/pull-requests/9)                                                                   |
| event                | ⏰ PR: [osmanov/pecl-event#19](https://bitbucket.org/osmanov/pecl-event/pull-requests/19)                                                           |
| excimer              | ?                                                                                                                                                  |
| expect               | ?                                                                                                                                                  |
| fam                  | ❌ Abandoned                                                                                                                                        |
| fann                 | ?                                                                                                                                                  |
| ffi                  | ❌ Abandoned                                                                                                                                        |
| Fileinfo             | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| filter               | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| framegrab            | ?                                                                                                                                                  |
| FreeImage            | ❌ Abandoned                                                                                                                                        |
| fribidi              | ❌ Abandoned                                                                                                                                        |
| funcall              | ❌ Abandoned                                                                                                                                        |
| functional           | ?                                                                                                                                                  |
| fuse                 | ❌ Abandoned                                                                                                                                        |
| GDChart              | ❌ Abandoned                                                                                                                                        |
| gearman              | ?                                                                                                                                                  |
| gender               | ?                                                                                                                                                  |
| geoip                | ?                                                                                                                                                  |
| geospatial           | ✅ Supported: [php-geospatial/geospatial](https://packagist.org/packages/php-geospatial/geospatial)                                                 |
| gmagick              | ?                                                                                                                                                  |
| gnupg                | ?                                                                                                                                                  |
| gpio                 | ✅ Supported: [embedded-php/gpio](https://packagist.org/packages/embedded-php/gpio)                                                                 |
| graphdat             | ?                                                                                                                                                  |
| gRPC                 | ?                                                                                                                                                  |
| gupnp                | ❌ Abandoned                                                                                                                                        |
| handlebars           | ?                                                                                                                                                  |
| haru                 | ❌ Abandoned                                                                                                                                        |
| hash                 | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| hdr_histogram        | ⏰ Coming soon: [beberlei/hdrhistogram](https://packagist.org/packages/beberlei/hdrhistogram)                                                       |
| hidef                | ❌ Abandoned                                                                                                                                        |
| hprose               | ?                                                                                                                                                  |
| hrtime               | ⏰ PR: [php/pecl-datetime-hrtime#1](https://github.com/php/pecl-datetime-hrtime/pull/1)                                                             |
| html_parse           | ❌ Abandoned                                                                                                                                        |
| htscanner            | ❌ Abandoned                                                                                                                                        |
| http_message         | ?                                                                                                                                                  |
| huffman              | ❌ Abandoned                                                                                                                                        |
| i2c                  | ✅ Supported: [embedded-php/i2c](https://packagist.org/packages/embedded-php/i2c)                                                                   |
| ibm_db2              | ?                                                                                                                                                  |
| ice                  | ?                                                                                                                                                  |
| id3                  | ❌ Abandoned                                                                                                                                        |
| idn                  | ?                                                                                                                                                  |
| igbinary             | ?                                                                                                                                                  |
| imagick              | ⏰ PR: [Imagick/imagick#688](https://github.com/Imagick/imagick/pull/688)                                                                           |
| imap                 | ?                                                                                                                                                  |
| imlib2               | ❌ Abandoned                                                                                                                                        |
| immutable_cache      | ?                                                                                                                                                  |
| IMS                  | ?                                                                                                                                                  |
| inclued              | ❌ Abandoned                                                                                                                                        |
| ingres               | ❌ Abandoned                                                                                                                                        |
| inotify              | ✅ Supported: [arnaud-lb/inotify](https://packagist.org/packages/arnaud-lb/inotify)                                                                 |
| intercept            | ?                                                                                                                                                  |
| intl                 | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| ion                  | ?                                                                                                                                                  |
| ip2location          | ?                                                                                                                                                  |
| ip2proxy             | ?                                                                                                                                                  |
| ircclient            | ❌ Abandoned                                                                                                                                        |
| isis                 | ?                                                                                                                                                  |
| jsmin                | ?                                                                                                                                                  |
| json                 | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| json_post            | ?                                                                                                                                                  |
| jsonc                | ?                                                                                                                                                  |
| jsond                | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| jsonnet              | ?                                                                                                                                                  |
| jsonpath             | ?                                                                                                                                                  |
| Judy                 | ❌ Abandoned                                                                                                                                        |
| kadm5                | ❌ Abandoned                                                                                                                                        |
| krb5                 | ?                                                                                                                                                  |
| KTaglib              | ❌ Abandoned                                                                                                                                        |
| lapack               | ❌ Abandoned                                                                                                                                        |
| lchash               | ❌ Abandoned                                                                                                                                        |
| leveldb              | ?                                                                                                                                                  |
| libevent             | ❌ Abandoned                                                                                                                                        |
| libsodium            | ?                                                                                                                                                  |
| lua                  | ?                                                                                                                                                  |
| LuaSandbox           | ?                                                                                                                                                  |
| lzf                  | ?                                                                                                                                                  |
| mailparse            | ✅ Supported: [pecl/mailparse](https://packagist.org/packages/pecl/mailparse)                                                                       |
| markdown             | ?                                                                                                                                                  |
| maxdb                | ❌ Abandoned                                                                                                                                        |
| maxminddb            | ?                                                                                                                                                  |
| mcrypt               | ⏰ PR: [php/pecl-encryption-mcrypt#20](https://github.com/php/pecl-encryption-mcrypt/pull/20)                                                       |
| mcrypt_filter        | ?                                                                                                                                                  |
| mcve                 | ❌ Abandoned                                                                                                                                        |
| mdbtools             | ❌ Abandoned                                                                                                                                        |
| memcache             | ⏰ PR: [websupport-sk/pecl-memcache#116](https://github.com/websupport-sk/pecl-memcache/pull/116)                                                   |
| memcached            | ⏰ PR [php-memcached-dev/php-memcached#560](https://github.com/php-memcached-dev/php-memcached/pull/560) was merged, but not yet added to Packagist |
| memoize              | ?                                                                                                                                                  |
| memprof              | ✅ Supported: [arnaud-lb/memprof](https://packagist.org/packages/arnaud-lb/memprof)                                                                 |
| memsession           | ❌ Abandoned                                                                                                                                        |
| memtrack             | ❌ Abandoned                                                                                                                                        |
| meta                 | ?                                                                                                                                                  |
| mnogosearch          | ❌ Abandoned                                                                                                                                        |
| mogilefs             | ?                                                                                                                                                  |
| Molten               | ?                                                                                                                                                  |
| mongo                | ❌ Abandoned                                                                                                                                        |
| mongodb              | ✅ Supported: [mongodb/mongodb-extension](https://packagist.org/packages/mongodb/mongodb-extension)                                                 |
| mono                 | ❌ Abandoned                                                                                                                                        |
| Mosquitto            | ?                                                                                                                                                  |
| mqseries             | ?                                                                                                                                                  |
| msgpack              | ?                                                                                                                                                  |
| mustache             | ?                                                                                                                                                  |
| mysql                | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| mysql_xdevapi        | ?                                                                                                                                                  |
| mysqlnd_azure        | ?                                                                                                                                                  |
| mysqlnd_krb          | ?                                                                                                                                                  |
| mysqlnd_memcache     | ❌ Abandoned                                                                                                                                        |
| mysqlnd_ms           | ❌ Abandoned                                                                                                                                        |
| mysqlnd_ngen         | ❌ Abandoned                                                                                                                                        |
| mysqlnd_qc           | ❌ Abandoned                                                                                                                                        |
| mysqlnd_uh           | ❌ Abandoned                                                                                                                                        |
| namazu               | ?                                                                                                                                                  |
| ncurses              | ❌ Abandoned                                                                                                                                        |
| Net_Gopher           | ❌ Abandoned                                                                                                                                        |
| netools              | ❌ Abandoned                                                                                                                                        |
| newt                 | ❌ Abandoned                                                                                                                                        |
| nsq                  | ?                                                                                                                                                  |
| oauth                | ?                                                                                                                                                  |
| ocal                 | ?                                                                                                                                                  |
| oci8                 | ?                                                                                                                                                  |
| odbtp                | ❌ Abandoned                                                                                                                                        |
| oggvorbis            | ❌ Abandoned                                                                                                                                        |
| openal               | ?                                                                                                                                                  |
| opencensus           | ?                                                                                                                                                  |
| opendirectory        | ❌ Abandoned                                                                                                                                        |
| opengl               | ?                                                                                                                                                  |
| openswoole           | ?                                                                                                                                                  |
| opentelemetry        | ?                                                                                                                                                  |
| operator             | ❌ Abandoned                                                                                                                                        |
| optimizer            | ❌ Abandoned                                                                                                                                        |
| orng                 | ❌ Abandoned                                                                                                                                        |
| PAM                  | ?                                                                                                                                                  |
| panda                | ❌ Abandoned                                                                                                                                        |
| Paradox              | ❌ Abandoned                                                                                                                                        |
| parallel             | ?                                                                                                                                                  |
| params               | ?                                                                                                                                                  |
| parle                | ?                                                                                                                                                  |
| Parse_Tree           | ❌ Abandoned                                                                                                                                        |
| parsekit             | ❌ Abandoned                                                                                                                                        |
| pcov                 | ✅ Supported: [pecl/pcov](https://packagist.org/packages/pecl/pcov)                                                                                 |
| pcs                  | ?                                                                                                                                                  |
| pcsc                 | ?                                                                                                                                                  |
| pdflib               | ❌ Abandoned                                                                                                                                        |
| PDO                  | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| PDO_4D               | ❌ Abandoned                                                                                                                                        |
| PDO_CUBRID           | ?                                                                                                                                                  |
| PDO_DBLIB            | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| PDO_FIREBIRD         | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| PDO_IBM              | ?                                                                                                                                                  |
| PDO_IDS              | ❌ Possibly abandoned - [gives a 404 on PECL](https://pecl.php.net/package/PDO_IDS)                                                                 |
| PDO_MYSQL            | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| PDO_OCI              | ?                                                                                                                                                  |
| PDO_ODBC             | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| PDO_PGSQL            | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| PDO_SQLANYWHERE      | ?                                                                                                                                                  |
| PDO_SQLITE           | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| pdo_sqlsrv           | ?                                                                                                                                                  |
| PDO_TAOS             | ?                                                                                                                                                  |
| pdo_user             | ❌ Abandoned                                                                                                                                        |
| PECL_Gen             | ❌ Abandoned                                                                                                                                        |
| pecl_http            | ?                                                                                                                                                  |
| perforce             | ?                                                                                                                                                  |
| perl                 | ❌ Abandoned                                                                                                                                        |
| Phalcon              | ?                                                                                                                                                  |
| phar                 | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| phdfs                | ❌ Abandoned                                                                                                                                        |
| PHK                  | ❌ Abandoned                                                                                                                                        |
| php_trie             | ?                                                                                                                                                  |
| phpy                 | ?                                                                                                                                                  |
| pinpoint_php         | ?                                                                                                                                                  |
| PKCS11               | ?                                                                                                                                                  |
| pledge               | ?                                                                                                                                                  |
| POP3                 | ❌ Abandoned                                                                                                                                        |
| pq                   | ?                                                                                                                                                  |
| proctitle            | ❌ Abandoned                                                                                                                                        |
| propro               | ?                                                                                                                                                  |
| protobuf             | ?                                                                                                                                                  |
| protocolbuffers      | ❌ Abandoned                                                                                                                                        |
| ps                   | ?                                                                                                                                                  |
| pspell               | ?                                                                                                                                                  |
| psr                  | ?                                                                                                                                                  |
| pthreads             | ❌ Abandoned                                                                                                                                        |
| python               | ❌ Abandoned                                                                                                                                        |
| qb                   | ❌ Abandoned                                                                                                                                        |
| qqwry                | ?                                                                                                                                                  |
| quickhash            | ?                                                                                                                                                  |
| radius               | ?                                                                                                                                                  |
| raphf                | ?                                                                                                                                                  |
| rar                  | ?                                                                                                                                                  |
| rdkafka              | ✅ Supported: [rdkafka/rdkafka](https://packagist.org/packages/rdkafka/rdkafka)                                                                     |
| re2                  | ❌ Abandoned                                                                                                                                        |
| redis                | ✅ Supported: [phpredis/phpredis](https://packagist.org/packages/phpredis/phpredis)                                                                 |
| ref                  | ?                                                                                                                                                  |
| request              | ?                                                                                                                                                  |
| riak                 | ❌ Abandoned                                                                                                                                        |
| rnp                  | ?                                                                                                                                                  |
| rpminfo              | ✅ Supported: [remi/rpminfo](https://packagist.org/packages/remi/rpminfo)                                                                           |
| rpmreader            | ❌ Abandoned                                                                                                                                        |
| rrd                  | ?                                                                                                                                                  |
| rsvg                 | ❌ Abandoned                                                                                                                                        |
| rsync                | ❌ Abandoned                                                                                                                                        |
| runkit               | ❌ Abandoned                                                                                                                                        |
| runkit7              | ?                                                                                                                                                  |
| sam                  | ❌ Abandoned                                                                                                                                        |
| sandbox              | ?                                                                                                                                                  |
| sasl                 | ?                                                                                                                                                  |
| scoutapm             | ?                                                                                                                                                  |
| scream               | ❌ Abandoned                                                                                                                                        |
| scrypt               | ?                                                                                                                                                  |
| sdl                  | ?                                                                                                                                                  |
| sdl_image            | ?                                                                                                                                                  |
| sdl_mixer            | ?                                                                                                                                                  |
| sdl_ttf              | ?                                                                                                                                                  |
| sdo                  | ❌ Possibly abandoned - [gives a 404 on PECL](https://pecl.php.net/package/sdo)                                                                     |
| SeasClick            | ?                                                                                                                                                  |
| SeasLog              | ?                                                                                                                                                  |
| SeasSnowflake        | ?                                                                                                                                                  |
| selinux              | ✅ Supported: [pecl/selinux](https://packagist.org/packages/pecl/selinux)                                                                           |
| shape                | ?                                                                                                                                                  |
| simdjson             | ?                                                                                                                                                  |
| simple_kafka_client  | ?                                                                                                                                                  |
| skywalking           | ?                                                                                                                                                  |
| skywalking_agent     | ?                                                                                                                                                  |
| smbclient            | ?                                                                                                                                                  |
| solr                 | ?                                                                                                                                                  |
| sphinx               | ❌ Abandoned                                                                                                                                        |
| spi                  | ✅ Supported: [embedded-php/spi](https://packagist.org/packages/embedded-php/spi)                                                                   |
| spidermonkey         | ❌ Abandoned                                                                                                                                        |
| SPL_Types            | ❌ Abandoned                                                                                                                                        |
| spplus               | ?                                                                                                                                                  |
| spread               | ?                                                                                                                                                  |
| SQLite               | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| sqlite3              | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| sqlsrv               | ?                                                                                                                                                  |
| ssdeep               | ?                                                                                                                                                  |
| ssh2                 | ?                                                                                                                                                  |
| stackdriver_debugger | ?                                                                                                                                                  |
| statgrab             | ?                                                                                                                                                  |
| stats                | ❌ Abandoned                                                                                                                                        |
| stem                 | ?                                                                                                                                                  |
| stomp                | ?                                                                                                                                                  |
| strict               | ❌ Abandoned                                                                                                                                        |
| sundown              | ❌ Abandoned                                                                                                                                        |
| svm                  | ?                                                                                                                                                  |
| svn                  | ?                                                                                                                                                  |
| swish                | ❌ Abandoned                                                                                                                                        |
| swoole               | ?                                                                                                                                                  |
| swoole_serialize     | ?                                                                                                                                                  |
| syck                 | ?                                                                                                                                                  |
| sync                 | ?                                                                                                                                                  |
| taint                | ?                                                                                                                                                  |
| tcc                  | ?                                                                                                                                                  |
| TCLink               | ?                                                                                                                                                  |
| tcpwrap              | ?                                                                                                                                                  |
| tdb                  | ?                                                                                                                                                  |
| teds                 | ?                                                                                                                                                  |
| Tensor               | ?                                                                                                                                                  |
| termbox              | ?                                                                                                                                                  |
| TextCat              | ?                                                                                                                                                  |
| tidy                 | ❌ Abandoned                                                                                                                                        |
| timecop              | ?                                                                                                                                                  |
| timezonedb           | ⏰ PR: [php/pecl-datetime-timezonedb#12](https://github.com/php/pecl-datetime-timezonedb/pull/12)                                                   |
| tk                   | ❌ Abandoned                                                                                                                                        |
| tokyo_tyrant         | ❌ Abandoned                                                                                                                                        |
| trace                | ?                                                                                                                                                  |
| trader               | ?                                                                                                                                                  |
| translit             | ?                                                                                                                                                  |
| tvision              | ?                                                                                                                                                  |
| txforward            | ❌ Abandoned                                                                                                                                        |
| uart                 | ✅ Supported: [embedded-php/uart](https://packagist.org/packages/embedded-php/uart)                                                                 |
| udis86               | ❌ Abandoned                                                                                                                                        |
| ui                   | ?                                                                                                                                                  |
| uopz                 | ?                                                                                                                                                  |
| uploadprogress       | ?                                                                                                                                                  |
| uri_template         | ❌ Abandoned                                                                                                                                        |
| uuid                 | ✅ Supported: [pecl/uuid](https://packagist.org/packages/pecl/uuid)                                                                                 |
| uv                   | ?                                                                                                                                                  |
| v8                   | ?                                                                                                                                                  |
| v8js                 | ?                                                                                                                                                  |
| Valkyrie             | ❌ Abandoned                                                                                                                                        |
| var_representation   | ?                                                                                                                                                  |
| varnish              | ?                                                                                                                                                  |
| vips                 | ?                                                                                                                                                  |
| vld                  | ?                                                                                                                                                  |
| vpopmail             | ❌ Abandoned                                                                                                                                        |
| wasm                 | ?                                                                                                                                                  |
| wbxml                | ❌ Abandoned                                                                                                                                        |
| Weakref              | ❌ Abandoned                                                                                                                                        |
| weakreference_bc     | ?                                                                                                                                                  |
| win32ps              | ❌ Abandoned                                                                                                                                        |
| win32ps_dll          | ❌ Abandoned                                                                                                                                        |
| win32service         | ?                                                                                                                                                  |
| win32std             | ❌ Abandoned                                                                                                                                        |
| WinBinder            | ?                                                                                                                                                  |
| WinCache             | ?                                                                                                                                                  |
| wxwidgets            | ❌ Abandoned                                                                                                                                        |
| xattr                | ?                                                                                                                                                  |
| xcommerce            | ❌ Abandoned                                                                                                                                        |
| Xdebug               | ✅ Supported: [xdebug/xdebug](https://packagist.org/packages/xdebug/xdebug)                                                                         |
| xdiff                | ?                                                                                                                                                  |
| xhprof               | ?                                                                                                                                                  |
| xlswriter            | ?                                                                                                                                                  |
| xmldiff              | ?                                                                                                                                                  |
| xmlReader            | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| xmlrpc               | ❌ Abandoned                                                                                                                                        |
| XMLRPCi              | ?                                                                                                                                                  |
| xmlwriter            | ❌ Core - see [php/pie#133](https://github.com/php/pie/issues/133)                                                                                  |
| xmms                 | ❌ Abandoned                                                                                                                                        |
| xmp                  | ❌ Abandoned                                                                                                                                        |
| xpass                | ✅ Supported: [remi/xpass](https://packagist.org/packages/remi/xpass)                                                                               |
| xrange               | ❌ Abandoned                                                                                                                                        |
| xslcache             | ❌ Abandoned                                                                                                                                        |
| xxtea                | ?                                                                                                                                                  |
| yac                  | ?                                                                                                                                                  |
| yaconf               | ?                                                                                                                                                  |
| Yaf                  | ?                                                                                                                                                  |
| yami                 | ❌ Abandoned                                                                                                                                        |
| yaml                 | ⏰ PR: [php/pecl-file_formats-yaml#88](https://github.com/php/pecl-file_formats-yaml/pull/88)                                                       |
| yar                  | ?                                                                                                                                                  |
| yaz                  | ?                                                                                                                                                  |
| yp                   | ?                                                                                                                                                  |
| ZendOptimizerPlus    | ❌ Possibly abandoned - [gives a 404 on PECL](https://pecl.php.net/package/ZendOptimizerPlus)                                                       |
| zephir_parser        | ?                                                                                                                                                  |
| zeroconf             | ?                                                                                                                                                  |
| zip                  | ?                                                                                                                                                  |
| zlib_filter          | ?                                                                                                                                                  |
| zmq                  | ❌ Abandoned                                                                                                                                        |
| zookeeper            | ?                                                                                                                                                  |
| zstd                 | ✅ Supported: [kjdev/zstd](https://packagist.org/packages/kjdev/zstd)                                                                               |
