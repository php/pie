--TEST--
test1() Basic test
--EXTENSIONS--
pie_test_ext
--FILE--
<?php
$ret = test1();

var_dump($ret);
?>
--EXPECT--
The extension pie_test_ext is loaded and working!
NULL
