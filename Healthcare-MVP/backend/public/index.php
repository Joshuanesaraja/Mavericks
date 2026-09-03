<?php

session_start();

require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Routes/Router.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

$request = $_GET['request'] ?? '';

if ($request === '') {
    $request = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
}

// Read and decode JSON body once.
$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    $input = [];
}

Router::handle($method, $request, $input);