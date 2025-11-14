# An approximately reproducible, but primarily isolated, environment for
# running the acceptance tests:
#
# GITHUB_TOKEN=$(composer config --global --auth github-oauth.github.com) docker buildx build --file .github/pie-behaviour-tests/Dockerfile --secret id=GITHUB_TOKEN,env=GITHUB_TOKEN -t pie-behat-test .
# docker run --volume .:/github/workspace -ti pie-behat-test
FROM alpine/git:v2.49.1 AS clone_ext_repo

RUN cd / && git clone https://github.com/asgrim/example-pie-extension.git

FROM boxproject/box:4.6.10 AS build_pie_phar

RUN apk add git
COPY . /app
RUN cd /app && /box.phar compile

FROM ubuntu:24.04 AS default

# Add the `unzip` package which PIE uses to extract .zip files
RUN export DEBIAN_FRONTEND="noninteractive"; \
    set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      unzip curl jq wget g++ gcc make autoconf libtool bison re2c pkg-config \
      ca-certificates libxml2-dev libssl-dev; \
    update-ca-certificates ; \
    rm -rf /var/lib/apt/lists/*

# Compile PHP
ARG PHP_VERSION=8.4
RUN mkdir -p /usr/local/src/php; \
    cd /usr/local/src/php; \
    FULL_LATEST_VERSION=`curl -fsSL "https://www.php.net/releases/index.php?json&max=1&version=$PHP_VERSION" | jq -r '.[].source[]|select(.filename|endswith(".gz")).filename'`; \
    wget -O php.tgz "https://www.php.net/distributions/$FULL_LATEST_VERSION" ; \
    tar zxf php.tgz ; \
    rm php.tgz ; \
    ls -l ; \
    cd * ; \
    ls -l ; \
    ./buildconf --force ; \
    ./configure --without-sqlite3 --disable-pdo --disable-dom --disable-xml --disable-xmlreader --disable-xmlwriter --disable-json --with-openssl ; \
    make -j$(nproc) ; \
    make install

RUN touch /usr/local/lib/php.ini

COPY --from=ghcr.io/php/pie:bin /pie /usr/bin/pie
RUN pie install xdebug/xdebug

COPY --from=clone_ext_repo /example-pie-extension /example-pie-extension

WORKDIR /github/workspace

COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN --mount=type=secret,id=GITHUB_TOKEN,env=GITHUB_TOKEN \
    composer config --global --auth github-oauth.github.com $GITHUB_TOKEN

COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie
COPY --from=build_pie_phar /app/pie.phar /usr/local/bin/pie.original

ENV USING_PIE_BEHAT_DOCKERFILE=1
ENTRYPOINT ["php", "vendor/bin/behat"]
CMD ["--no-snippets"]
