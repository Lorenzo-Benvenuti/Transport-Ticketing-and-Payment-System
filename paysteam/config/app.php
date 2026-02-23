<?php
require_once __DIR__ . '/../../shared/env.php';
load_env(__DIR__ . '/../.env');

$env = getenv('APP_ENV') ?: 'prod';
$isDev = ($env === 'dev');

/**
 * Error handling:
 * - dev: show errors
 * - prod: hide errors, log to file
*/
ini_set('display_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');

// Where PHP writes error logs (file will be created if it doesn't exist)
$logPath = __DIR__ . '/../storage/logs/php-error.log';
@mkdir(dirname($logPath), 0777, true);
ini_set('error_log', $logPath);

error_reporting($isDev ? E_ALL : (E_ALL & ~E_DEPRECATED & ~E_STRICT));
