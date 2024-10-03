FROM ghcr.io/roave/docbooktool:1.16.0 AS builder

COPY ./.github/docs/templates /docs-src/templates
COPY ./docs /docs-src/book
COPY ./.github/docs/index-front-matter.yml /docs-src/book/index.md
RUN --mount=type=bind,source=./README.md,target=/tmp/index.md \
    cat /tmp/index.md >> /docs-src/book/index.md
COPY ./features /docs-src/features

FROM builder AS built

RUN bin/docbook-tool --html

FROM scratch AS output

COPY --from=built /docs-package /
