<?php
echo "PHP: " . phpversion() . "<br>";
echo "INI: " . php_ini_loaded_file() . "<br>";
echo "EXTDIR: " . ini_get('extension_dir') . "<br>";
echo "SCANNED: " . php_ini_scanned_files() . "<br>";
foreach (['curl','openssl','mysqli','mbstring'] as $e) {
    echo $e . ": ";
    var_dump(extension_loaded($e));
    echo "<br>";
}
