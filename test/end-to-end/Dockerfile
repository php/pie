FROM boxproject/box:4.7.0 AS build_pie_phar
RUN apk add git
COPY . /app
RUN cd /app && touch creating_this_means_phar_will_never_be_verified && /box.phar compile

FROM alpine AS test_pie_installs_build_tools_on_alpine
RUN apk add php php-phar php-mbstring php-iconv php-openssl bzip2-dev libbz2
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install --auto-install-build-tools -v php/bz2
RUN apk del .php-pie-deps
RUN pie show

FROM fedora AS test_pie_installs_build_tools_on_fedora
RUN dnf install -y php php-pecl-zip unzip bzip2-devel
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install --auto-install-build-tools -v php/bz2
RUN pie show

FROM ubuntu AS test_pie_installs_build_tools_on_ubuntu
RUN apt-get update && apt-get install -y php unzip libbz2-dev
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install --auto-install-build-tools -v php/bz2
RUN pie show

FROM homebrew/brew AS test_pie_installs_build_tools_with_brew
RUN brew install php
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
USER root
RUN apt-get update && apt-get install -y unzip
RUN apt-get remove --allow-remove-essential -y apt
USER linuxbrew
RUN pie install --auto-install-build-tools -v asgrim/example-pie-extension
RUN pie show

FROM ubuntu AS test_pie_installs_system_deps_on_ubuntu
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
RUN pie install -v --auto-install-system-dependencies php/sodium

FROM alpine AS test_pie_installs_system_deps_on_alpine
RUN apk add php php-phar php-mbstring php-iconv php-openssl bzip2-dev libbz2 build-base autoconf bison re2c libtool php84-dev
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install -v --auto-install-system-dependencies php/sodium

FROM fedora AS test_pie_installs_system_deps_on_fedora
RUN dnf install -y php php-pecl-zip unzip gcc make autoconf bison re2c libtool php-devel
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
RUN pie install -v --auto-install-system-dependencies php/sodium
