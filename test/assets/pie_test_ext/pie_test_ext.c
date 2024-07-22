/* pie_test_ext extension for PHP (c) 2024 PHP Foundation */

#ifdef HAVE_CONFIG_H
# include <config.h>
#endif

#include "php.h"
#include "ext/standard/info.h"
#include "php_pie_test_ext.h"
#include "pie_test_ext_arginfo.h"

/* For compatibility with older PHP versions */
#ifndef ZEND_PARSE_PARAMETERS_NONE
#define ZEND_PARSE_PARAMETERS_NONE() \
	ZEND_PARSE_PARAMETERS_START(0, 0) \
	ZEND_PARSE_PARAMETERS_END()
#endif

/* {{{ void test1() */
PHP_FUNCTION(test1)
{
	ZEND_PARSE_PARAMETERS_NONE();

	php_printf("The extension %s is loaded and working!\r\n", "pie_test_ext");
}
/* }}} */

/* {{{ PHP_RINIT_FUNCTION */
PHP_RINIT_FUNCTION(pie_test_ext)
{
#if defined(ZTS) && defined(COMPILE_DL_PIE_TEST_EXT)
	ZEND_TSRMLS_CACHE_UPDATE();
#endif

	return SUCCESS;
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION */
PHP_MINFO_FUNCTION(pie_test_ext)
{
	php_info_print_table_start();
	php_info_print_table_row(2, "pie_test_ext support", "enabled");
	php_info_print_table_end();
}
/* }}} */

/* {{{ pie_test_ext_module_entry */
zend_module_entry pie_test_ext_module_entry = {
	STANDARD_MODULE_HEADER,
	"pie_test_ext",					/* Extension name */
	ext_functions,					/* zend_function_entry */
	NULL,							/* PHP_MINIT - Module initialization */
	NULL,							/* PHP_MSHUTDOWN - Module shutdown */
	PHP_RINIT(pie_test_ext),			/* PHP_RINIT - Request initialization */
	NULL,							/* PHP_RSHUTDOWN - Request shutdown */
	PHP_MINFO(pie_test_ext),			/* PHP_MINFO - Module info */
	PHP_PIE_TEST_EXT_VERSION,		/* Version */
	STANDARD_MODULE_PROPERTIES
};
/* }}} */

#ifdef COMPILE_DL_PIE_TEST_EXT
# ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
# endif
ZEND_GET_MODULE(pie_test_ext)
#endif
