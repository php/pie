FROM scratch AS standalone-binary

# @TODO change to --chmod=+x when https://github.com/moby/buildkit/pull/5380 is released
COPY --chmod=0755 pie.phar /pie
