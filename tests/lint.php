<?php
$n=0;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__)))as$f){if($f->getExtension()==='php'){token_get_all(file_get_contents($f->getPathname()),TOKEN_PARSE);$n++;}}
echo "OK: $n PHP files parsed on PHP ".PHP_VERSION."\n";
