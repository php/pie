#!/usr/bin/env bash

ARGS="$*"

case "$ARGS" in
    "-n -r echo \"PHP\";")
      echo "PHP";
      exit 0
      ;;
    "-n -r echo PHP_VERSION;")
      echo "5.6.40-90+ubuntu24.04.1+deb.sury.org+1";
      exit 0
      ;;
    "-n -r echo PHP_MAJOR_VERSION . \".\" . PHP_MINOR_VERSION . \".\" . PHP_RELEASE_VERSION;")
      echo "5.6.40";
      exit 0
      ;;
    "-n -r echo PHP_MAJOR_VERSION . \".\" . PHP_MINOR_VERSION;")
      echo "5.6";
      exit 0
      ;;
    *)
      echo "unknown fake php command: $ARGS"
      exit 1
esac

