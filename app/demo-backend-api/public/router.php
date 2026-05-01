<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $url = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $file = __DIR__ . $url;
    if (is_file($file)) {
        return false;
    }
}

$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
require_once __DIR__ . '/index.php';
