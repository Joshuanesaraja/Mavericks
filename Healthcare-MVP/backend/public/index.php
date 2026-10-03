<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_set_cookie_params([
    'samesite' => 'None',
    'secure' => true,
    'httponly' => true,
]);

session_start();

require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Routes/Router.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

$request = $_GET['request'] ?? '';

if ($request === '') {
    $request = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    if (str_starts_with($request, 'api/')) {
        $request = substr($request, 4);
    }
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
