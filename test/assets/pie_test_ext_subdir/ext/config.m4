PHP_ARG_ENABLE([pie_test_ext_subdir],
  [whether to enable pie_test_ext_subdir support],
  [AS_HELP_STRING([--enable-pie_test_ext_subdir],
    [Enable pie_test_ext_subdir support])],
  [no])

if test "$PHP_PIE_TEST_EXT" != "no"; then
  AC_DEFINE(HAVE_PIE_TEST_EXT, 1, [ Have pie_test_ext_subdir support ])

  PHP_NEW_EXTENSION([pie_test_ext_subdir],
    [pie_test_ext_subdir.c],
    [$ext_shared],,
    [-DZEND_ENABLE_STATIC_TSRMLS_CACHE=1])
fi
