<?php
// CI helper: run one API request in a fresh PHP process as a logged-in user.
// Usage: php tools/api_call.php <endpoint.php> <action> '<json body>'
// Prints the endpoint's JSON response.
declare(strict_types=1);
[$self, $endpoint, $action, $body] = array_pad($argv, 4, '{}');

session_id('ci' . getmypid());
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'ci-token';
$_GET['action'] = $action;
$GLOBALS['__ci_body'] = $body;
@session_start();
$_SESSION['user_id'] = 1;
$_SESSION['csrf'] = 'ci-token';
session_write_close();

// php://input can't be faked in CLI: endpoints read the body via growth_request_body()
define('GROWTH_CI_BODY', $body);
chdir(dirname(__DIR__));
require dirname(__DIR__) . '/' . basename($endpoint);
