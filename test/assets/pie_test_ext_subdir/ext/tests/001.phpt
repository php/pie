--TEST--
Check if pie_test_ext_subdir is loaded
--EXTENSIONS--
pie_test_ext_subdir
--FILE--
<?php
echo 'The extension "pie_test_ext_subdir" is available';
?>
--EXPECT--
The extension "pie_test_ext_subdir" is available
