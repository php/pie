PHP_ARG_ENABLE([pie_test_ext],
  [whether to enable pie_test_ext support],
  [AS_HELP_STRING([--enable-pie_test_ext],
    [Enable pie_test_ext support])],
  [no])

if test "$PHP_PIE_TEST_EXT" != "no"; then
  AC_DEFINE(HAVE_PIE_TEST_EXT, 1, [ Have pie_test_ext support ])

  PHP_NEW_EXTENSION([pie_test_ext],
    [pie_test_ext.c],
    [$ext_shared],,
    [-DZEND_ENABLE_STATIC_TSRMLS_CACHE=1])
fi
