--TEST--
Check if pie_test_ext is loaded
--EXTENSIONS--
pie_test_ext
--FILE--
<?php
echo 'The extension "pie_test_ext" is available';
?>
--EXPECT--
The extension "pie_test_ext" is available
