/* pie_test_ext extension for PHP (c) 2024 PHP Foundation */

#ifndef PHP_PIE_TEST_EXT_H
# define PHP_PIE_TEST_EXT_H

extern zend_module_entry pie_test_ext_module_entry;
# define phpext_pie_test_ext_ptr &pie_test_ext_module_entry

# define PHP_PIE_TEST_EXT_VERSION "0.1.0"

# if defined(ZTS) && defined(COMPILE_DL_PIE_TEST_EXT)
ZEND_TSRMLS_CACHE_EXTERN()
# endif

#endif	/* PHP_PIE_TEST_EXT_H */
