<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path === '/' || $path === '/index.php') { return false; }
if (is_file($file)) return false;
http_response_code(404); echo '404';
