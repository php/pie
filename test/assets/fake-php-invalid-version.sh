#!/usr/bin/env bash

ARGS="$*"

case "$ARGS" in
    "-n -r echo PHP_MAJOR_VERSION . \"\\n\" . PHP_MINOR_VERSION . \"\\n\" . PHP_RELEASE_VERSION . \"\\n\" . PHP_VERSION;")
      echo "5";
      echo "6";
      echo "40";
      echo "5.6.40-90+ubuntu24.04.1+deb.sury.org+1";
      exit 0
      ;;
    *)
      echo "unknown fake php command: $ARGS"
      exit 1
esac
