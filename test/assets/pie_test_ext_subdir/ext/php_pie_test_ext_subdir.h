/* pie_test_ext_subdir extension for PHP (c) 2024 PHP Foundation */

#ifndef PHP_PIE_TEST_EXT_SUBDIR_H
# define PHP_PIE_TEST_EXT_SUBDIR_H

extern zend_module_entry pie_test_ext_subdir_module_entry;
# define phpext_pie_test_ext_subdir_ptr &pie_test_ext_subdir_module_entry

# define PHP_PIE_TEST_EXT_VERSION "0.1.0"

# if defined(ZTS) && defined(COMPILE_DL_PIE_TEST_EXT_SUBDIR)
ZEND_TSRMLS_CACHE_EXTERN()
# endif

#endif	/* PHP_PIE_TEST_EXT_SUBDIR_H */
