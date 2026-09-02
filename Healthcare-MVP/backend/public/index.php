<?php

require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Routes/Router.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

$request = $_GET['request'] ?? '';

if ($request === '') {
    $request = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
}

Router::handle($method, $request);