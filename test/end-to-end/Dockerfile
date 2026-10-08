FROM boxproject/box:4.7.0 AS box_base
FROM alpine:3.24 AS alpine_base
FROM fedora:44 AS fedora_base
FROM ubuntu:26.04 AS ubuntu_base
FROM ubuntu:22.04 AS ubuntu_2204_base
FROM homebrew/brew:4.6.20 AS homebrew_base
FROM redhat/ubi9-minimal:9.8 AS ubi9_base

FROM box_base AS build_pie_phar
RUN apk add git
COPY . /app
RUN cd /app && touch creating_this_means_phar_will_never_be_verified && /box.phar compile

FROM alpine_base AS test_pie_installs_build_tools_on_alpine
RUN apk add php php-phar php-mbstring php-iconv php-openssl bzip2-dev libbz2
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install --auto-install-build-tools -v asgrim/example-pie-extension:2.0.5
RUN apk del .php-pie-deps
RUN pie show

FROM fedora_base AS test_pie_installs_build_tools_on_fedora
RUN dnf install -y php php-pecl-zip unzip bzip2-devel
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install --auto-install-build-tools -v asgrim/example-pie-extension:2.0.5
RUN pie show

FROM quay.io/rockylinux/rockylinux:10 AS test_pie_installs_build_tools_on_rocky
RUN dnf install -y php8.4-cli epel-release unzip
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install --auto-install-build-tools -vv asgrim/example-pie-extension:2.0.5
RUN pie show

FROM ubi9_base AS test_pie_installs_build_tools_on_ubi9
RUN microdnf module enable -y php:8.3 && microdnf install -y php-cli php-pecl-zip unzip
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install --auto-install-build-tools -v asgrim/example-pie-extension:2.0.5
RUN pie show

FROM ubuntu_base AS test_pie_installs_build_tools_on_ubuntu
RUN apt-get update && apt-get install -y php unzip libbz2-dev
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install --auto-install-build-tools -v asgrim/example-pie-extension:2.0.5
RUN pie show

FROM homebrew_base AS homebrew_base_with_pie
RUN brew update && brew install php
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
USER root
RUN rm -f /etc/apt/sources.list.d/github-cli.list && apt-get update && apt-get install -y unzip
RUN apt-get remove --allow-remove-essential -y apt
USER linuxbrew

FROM homebrew_base_with_pie AS test_pie_installs_build_tools_with_brew
RUN pie install --auto-install-build-tools -v asgrim/example-pie-extension:2.0.5
RUN pie show

FROM homebrew_base_with_pie AS test_pie_installs_extension_using_pcre_with_brew
RUN pie install --auto-install-build-tools -v apcu/apcu
RUN php -m | grep -i apcu

FROM ubuntu_base AS test_pie_installs_system_deps_on_ubuntu
RUN apt-get update && apt install -y unzip curl wget gcc make autoconf libtool bison re2c pkg-config libzip-dev libssl-dev libonig-dev
RUN mkdir -p /opt/php \
    && mkdir -p /tmp/php \
    && cd /tmp/php \
    && wget -O php.tgz https://www.php.net/distributions/php-8.4.17.tar.gz \
    && tar zxf php.tgz \
    && rm php.tgz \
    && cd * \
    && ./buildconf --force \
    && ./configure --prefix=/opt/php --disable-all --enable-phar --enable-filter --enable-mbstring --with-openssl --with-iconv --with-zip \
    && make -j$(nproc) \
    && make install
ENV PATH="$PATH:/opt/php/bin"
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install -v --auto-install-system-dependencies --force php/sodium

FROM alpine_base AS test_pie_installs_system_deps_on_alpine
RUN apk add php php-phar php-mbstring php-iconv php-openssl bzip2-dev libbz2 build-base autoconf bison re2c libtool php85-dev
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install -v --auto-install-system-dependencies --force php/sodium

FROM fedora_base AS test_pie_installs_system_deps_on_fedora
RUN dnf install -y php php-pecl-zip unzip gcc make autoconf bison re2c libtool php-devel
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install -v --auto-install-system-dependencies --force php/sodium

FROM ubuntu_2204_base AS test_pie_installs_on_php_with_three_argument_hash_file
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y php8.1-cli php8.1-dev unzip ca-certificates gcc make autoconf libtool
RUN php -r 'exit(version_compare(PHP_VERSION, "8.1.20", "<") ? 0 : 1);'
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install -v apcu/apcu
RUN pie show

FROM quay.io/rockylinux/rockylinux:8 AS test_lib64_detection_on_rocky8
RUN dnf module enable -y php:8.2 && dnf install -y php-cli php-devel unzip gcc gcc-c++ make autoconf bison flex libtool git cmake openssl-devel cyrus-sasl-devel
RUN git clone --depth 1 --branch 1.1.4 https://github.com/awesomized/libmemcached /tmp/libmemcached \
    && cmake -S /tmp/libmemcached -B /tmp/libmemcached/build -DBUILD_TESTING=OFF -DBUILD_DOCSONLY=OFF -DENABLE_SASL=ON \
    && cmake --build /tmp/libmemcached/build -j$(nproc) \
    && cmake --install /tmp/libmemcached/build
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install -v php-memcached/php-memcached
