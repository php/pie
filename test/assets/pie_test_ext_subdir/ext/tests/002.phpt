--TEST--
test1() Basic test
--EXTENSIONS--
pie_test_ext_subdir
--FILE--
<?php
$ret = test1();

var_dump($ret);
?>
--EXPECT--
The extension pie_test_ext_subdir is loaded and working!
NULL
