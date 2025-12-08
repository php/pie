---
title: 支持的扩展
order: 4
---

# 支持的扩展

由于 Packagist 是 PIE 包的新家，完整的支持、兼容 PIE 的扩展列表可以在以下位置找到：

* **[https://packagist.org/extensions](https://packagist.org/extensions)**

添加 PIE 支持的过程在[扩展维护者](extension-maintainers.md)文档中有记录。

## PECL 扩展迁移

PECL 仓库仍然有大量尚未添加 PIE 支持的扩展。这是 PECL 上托管的扩展列表，以及它们添加 PIE 支持的状态。如果您发现此处有过时的信息，请提交 [Pull Request](https://github.com/php/pie/pulls)。

### 已支持

以下扩展已经添加了 PIE 支持：

| PECL 扩展      | PIE 扩展                                                                                        |
|----------------|-----------------------------------------------------------------------------------------------------|
| amqp           | [php-amqp/php-amqp](https://packagist.org/packages/php-amqp/php-amqp)                               |
| APCu           | [apcu/apcu](https://packagist.org/packages/apcu/apcu)                                               |
| apfd           | [m6w6/ext-apfd](https://packagist.org/packages/m6w6/ext-apfd)                                       |
| ast            | [nikic/php-ast](https://packagist.org/packages/nikic/php-ast)                                       |
| bcmath         | php/bcmath                                                                                          |
| bitset         | [pecl/bitset](https://packagist.org/packages/pecl/bitset)                                           |
| brotli         | [kjdev/brotli](https://packagist.org/packages/kjdev/brotli)                                         |
| bz2            | php/bz2                                                                                             |
| calendar       | php/calendar                                                                                        |
| CSV            | [girgias/csv](https://packagist.org/packages/girgias/csv)                                           |
| ctype          | php/ctype                                                                                           |
| curl           | php/curl                                                                                            |
| dba            | php/dba                                                                                             |
| decimal        | [php-decimal/ext-decimal](https://packagist.org/packages/php-decimal/ext-decimal)                   |
| dio            | [pecl/dio](https://packagist.org/packages/pecl/dio)                                                 |
| dom            | php/dom                                                                                             |
| ds             | [php-ds/ext-ds](https://packagist.org/packages/php-ds/ext-ds)                                       |
| enchant        | php/enchant                                                                                         |
| exif           | php/exif                                                                                            |
| ev             | [osmanov/pecl-ev](https://packagist.org/packages/osmanov/pecl-ev)                                   |
| event          | [osmanov/pecl-event](https://packagist.org/packages/osmanov/pecl-event)                             |
| ffi            | php/ffi                                                                                             |
| geospatial     | [php-geospatial/geospatial](https://packagist.org/packages/php-geospatial/geospatial)               |
| gettext        | php/gettext                                                                                         |
| gmp            | php/gmp                                                                                             |
| gpio           | [embedded-php/gpio](https://packagist.org/packages/embedded-php/gpio)                               |
| i2c            | [embedded-php/i2c](https://packagist.org/packages/embedded-php/i2c)                                 |
| iconv          | php/iconv                                                                                           |
| imagick        | [imagick/imagick](https://packagist.org/packages/imagick/imagick)                                   |
| inotify        | [arnaud-lb/inotify](https://packagist.org/packages/arnaud-lb/inotify)                               |
| intl           | php/intl                                                                                            |
| jsonpath       | [supermetrics/jsonpath](https://packagist.org/packages/supermetrics/jsonpath)                       |
| ldap           | php/ldap                                                                                            |
| mailparse      | [pecl/mailparse](https://packagist.org/packages/pecl/mailparse)                                     |
| mbstring       | php/mbstring                                                                                        |
| mcrypt         | [pecl/mcrypt](https://packagist.org/packages/pecl/mcrypt)                                           |
| memcached      | [php-memcached/php-memcached](https://packagist.org/packages/php-memcached/php-memcached)           |
| memprof        | [arnaud-lb/memprof](https://packagist.org/packages/arnaud-lb/memprof)                               |
| mongodb        | [mongodb/mongodb-extension](https://packagist.org/packages/mongodb/mongodb-extension)               |
| mysqlnd        | php/mysqlnd                                                                                         |
| mysqli         | php/mysqli                                                                                          |
| opcache        | php/opcache                                                                                         |
| opentelemetry  | [open-telemetry/ext-opentelemetry](https://packagist.org/packages/open-telemetry/ext-opentelemetry) |
| parallel       | [pecl/parallel](https://packagist.org/packages/pecl/parallel)                                       |
| pcov           | [pecl/pcov](https://packagist.org/packages/pecl/pcov)                                               |
| pcntl          | php/pcntl                                                                                           |
| pdo            | php/pdo                                                                                             |
| pdo_mysql      | php/pdo_mysql                                                                                       |
| pdo_pgsql      | php/pdo_pgsql                                                                                       |
| pdo_sqlite     | php/pdo_sqlite                                                                                      |
| pgsql          | php/pgsql                                                                                           |
| posix          | php/posix                                                                                           |
| rdkafka        | [rdkafka/rdkafka](https://packagist.org/packages/rdkafka/rdkafka)                                   |
| readline       | php/readline                                                                                        |
| redis          | [phpredis/phpredis](https://packagist.org/packages/phpredis/phpredis)                               |
| relay          | [cachewerk/relay](https://packagist.org/packages/cachewerk/ext-relay)                               |
| rpminfo        | [remi/rpminfo](https://packagist.org/packages/remi/rpminfo)                                         |
| selinux        | [pecl/selinux](https://packagist.org/packages/pecl/selinux)                                         |
| session        | php/session                                                                                         |
| shmop          | php/shmop                                                                                           |
| simdjson       | [awesome/simdjson_plus](https://packagist.org/packages/awesome/simdjson_plus)                       |
| simplexml      | php/simplexml                                                                                       |
| snmp           | php/snmp                                                                                            |
| soap           | php/soap                                                                                            |
| sockets        | php/sockets                                                                                         |
| sodium         | php/sodium                                                                                          |                                                                              
| spi            | [embedded-php/spi](https://packagist.org/packages/embedded-php/spi)                                 |
| sqlite3        | php/sqlite3                                                                                         |
| swoole         | [swoole/swoole](https://packagist.org/packages/swoole/swoole)                                       |
| sysvmsg        | php/sysvmsg                                                                                         |
| sysvsem        | php/sysvsem                                                                                         |
| sysvshm        | php/sysvshm                                                                                         |
| tidy           | php/tidy                                                                                            |
| timezonedb     | [pecl/timezonedb](https://packagist.org/packages/pecl/timezonedb)                                   |
| translit       | [derickr/translit](https://packagist.org/packages/derickr/translit)                                 |
| uart           | [embedded-php/uart](https://packagist.org/packages/embedded-php/uart)                               |
| uuid           | [pecl/uuid](https://packagist.org/packages/pecl/uuid)                                               |
| vld            | [derickr/vld](https://packagist.org/packages/derickr/vld)                                           |
| win32service   | [win32service/win32service](https://packagist.org/packages/win32service/win32service)               |
| xattr          | [pecl/xattr](https://packagist.org/packages/pecl/xattr)                                             |
| Xdebug         | [xdebug/xdebug](https://packagist.org/packages/xdebug/xdebug)                                       |
| xlswriter      | [viest/xlswriter](https://packagist.org/packages/viest/xlswriter)                                   |
| xml            | php/xml                                                                                             |
| xmlreader      | php/xmlreader                                                                                       |
| xmlwriter      | php/xmlwriter                                                                                       |
| xsl            | php/xsl                                                                                             |
| xpass          | [remi/xpass](https://packagist.org/packages/remi/xpass)                                             |
| zip            | php/zip, [pecl/zip](https://packagist.org/packages/pecl/zip)                                        |
| zlib           | php/zlib                                                                                            |
| zstd           | [kjdev/zstd](https://packagist.org/packages/kjdev/zstd)                                             |

### 进行中

以下扩展已开始努力添加 PIE 支持：

| PECL 扩展      | 状态                                                                                           |
|----------------|--------------------------------------------------------------------------------------------------|
| base58         | ⏰ PR: [jasny/base58-php-ext#14](https://github.com/jasny/base58-php-ext/pull/14)                 |
| crypto         | ⏰ PR: [bukka/php-crypto#43](https://github.com/bukka/php-crypto/pull/43)                         |
| dbase          | ⏰ PR: [php/pecl-database-dbase#6](https://github.com/php/pecl-database-dbase/pull/6)             |
| hdr_histogram  | ⏰ 即将推出: [beberlei/hdrhistogram](https://packagist.org/packages/beberlei/hdrhistogram)     |
| hrtime         | ⏰ PR: [php/pecl-datetime-hrtime#1](https://github.com/php/pecl-datetime-hrtime/pull/1)           |
| memcache       | ⏰ PR: [websupport-sk/pecl-memcache#116](https://github.com/websupport-sk/pecl-memcache/pull/116) |
| yaml           | ⏰ PR: [php/pecl-file_formats-yaml#88](https://github.com/php/pecl-file_formats-yaml/pull/88)     |

### 未开始 / 未知

以下扩展存在于 PECL 上，但尚未添加 PIE 支持，或其状态未知。

* ahocorasick
* AOP
* ApacheAccessor
* apcu_bc
* APM
* augeas
* awscrt
* bloomy
* bsdiff
* bz2_filter
* cairo_wrapper
* cassandra
* cld
* clips
* clucene
* cmark
* componere
* couchbase
* courierauth
* crack_dll
* CUBRID
* cvsclient
* cybermut
* datadog_trace
* dazuko
* docblock
* doublemetaphone
* Druid
* ecasound
* ecma_intl
* eio
* env
* esmtp
* excimer
* expect
* fann
* framegrab
* functional
* gearman
* gender
* geoip
* gmagick
* gnupg
* graphdat
* gRPC
* handlebars
* hprose
* http_message
* ibm_db2
* ice
* idn
* igbinary
* imap
* immutable_cache
* IMS
* intercept
* ion
* ip2location
* ip2proxy
* isis
* jsmin
* json_post
* jsonc
* jsonnet
* krb5
* leveldb
* libsodium
* lua
* LuaSandbox
* lzf
* markdown
* maxminddb
* mcrypt_filter
* memoize
* meta
* mogilefs
* Molten
* Mosquitto
* mqseries
* msgpack
* mustache
* mysql_xdevapi
* mysqlnd_azure
* mysqlnd_krb
* namazu
* nsq
* oauth
* ocal
* oci8
* openal
* opencensus
* opengl
* openswoole
* PAM
* params
* parle
* pcs
* pcsc
* PDO_CUBRID
* PDO_IBM
* PDO_OCI
* PDO_SQLANYWHERE
* pdo_sqlsrv
* PDO_TAOS
* pecl_http
* perforce
* Phalcon
* php_trie
* phpy
* pinpoint_php
* PKCS11
* pledge
* pq
* propro
* protobuf
* ps
* pspell
* psr
* qqwry
* quickhash
* radius
* raphf
* rar
* ref
* request
* rnp
* rrd
* runkit7
* sandbox
* sasl
* scoutapm
* scrypt
* sdl
* sdl_image
* sdl_mixer
* sdl_ttf
* SeasClick
* SeasLog
* SeasSnowflake
* shape
* simple_kafka_client
* skywalking
* skywalking_agent
* smbclient
* solr
* spplus
* spread
* sqlsrv
* ssdeep
* ssh2
* stackdriver_debugger
* statgrab
* stem
* stomp
* svm
* svn
* swoole_serialize
* syck
* sync
* taint
* tcc
* TCLink
* tcpwrap
* tdb
* teds
* Tensor
* termbox
* TextCat
* timecop
* trace
* trader
* tvision
* ui
* uopz
* uploadprogress
* uv
* v8
* v8js
* var_representation
* varnish
* vips
* wasm
* weakreference_bc
* WinBinder
* WinCache
* xdiff
* xhprof
* xmldiff
* XMLRPCi
* xxtea
* yac
* yaconf
* Yaf
* yar
* yaz
* yp
* zephir_parser
* zeroconf
* zlib_filter
* zookeeper

### 已废弃

以下扩展被认为已废弃：

* amfext
* APC
* apd
* apn
* archive
* ares
* automap
* axis2
* bbcode
* bcompiler
* big_int
* binpack
* BLENC
* cairo
* chdb
* classkit
* coin_acceptor
* colorer
* crack
* cybercash
* cyrus
* DBDO
* dbplus
* DBus
* dbx
* dom_varimport
* drizzle
* enchant
* fam
* ffi
* FreeImage
* fribidi
* funcall
* fuse
* GDChart
* gupnp
* haru
* hidef
* html_parse
* htscanner
* huffman
* id3
* imlib2
* inclued
* ingres
* ircclient
* Judy
* kadm5
* KTaglib
* lapack
* lchash
* libevent
* maxdb
* mcve
* mdbtools
* memsession
* memtrack
* mnogosearch
* mongo
* mono
* mysqlnd_memcache
* mysqlnd_ms
* mysqlnd_ngen
* mysqlnd_qc
* mysqlnd_uh
* ncurses
* Net_Gopher
* netools
* newt
* odbtp
* oggvorbis
* opendirectory
* operator
* optimizer
* orng
* panda
* Paradox
* Parse_Tree
* parsekit
* pdflib
* PDO_4D
* PDO_IDS
* pdo_user
* PECL_Gen
* perl
* phdfs
* PHK
* POP3
* proctitle
* protocolbuffers
* pthreads
* python
* qb
* re2
* riak
* rpmreader
* rsvg
* rsync
* runkit
* sam
* scream
* sdo
* sphinx
* spidermonkey
* SPL_Types
* stats
* strict
* sundown
* swish
* tidy
* tk
* tokyo_tyrant
* txforward
* udis86
* uri_template
* Valkyrie
* vpopmail
* wbxml
* Weakref
* win32ps
* win32ps_dll
* win32std
* wxwidgets
* xcommerce
* xmlrpc
* xmms
* xmp
* xrange
* xslcache
* yami
* ZendOptimizerPlus
* zmq

