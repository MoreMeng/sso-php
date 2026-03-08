<?php
echo "mod_rewrite: " . (function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules()) ? "ENABLED" : "DISABLED/CHECK");
echo "<br>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "QUERY_STRING: " . $_SERVER['QUERY_STRING'] . "<br>";
echo "PAGE: " . ($_GET['page'] ?? 'NOT SET') . "<br>";
?>
