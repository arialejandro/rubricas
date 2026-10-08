<?php

// Router para `php -S` (pruebas locales sin Apache): sirve archivos estáticos de public/
// y manda todo lo demás a Laravel, sin depender del directorio de trabajo.
$publicPath = __DIR__.'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
