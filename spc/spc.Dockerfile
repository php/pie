# docker build --file spc.Dockerfile --tag pie-spc-test .
# docker run --rm -ti pie-spc-test
FROM php:7.4-cli

# need
RUN apt-get update && \
    apt-get install -y git

COPY pie.elf /usr/bin/pie

CMD ["pie", "install", "asgrim/example-pie-extension"]
