<?php
require_once __DIR__ . '/../../shared/env.php';
load_env(__DIR__ . '/../.env');

/**
 * Database connection (MySQLi).
 * Configuration is read from environment variables.
 *
 * Required:
 *  - DB_HOST
 *  - DB_USER
 *  - DB_PASS
 *  - DB_NAME
*/
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: '';
$password = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: '';

// Fail-fast su errori MySQLi (evita fallimenti silenziosi)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($host, $user, $password, $dbname);
$conn->set_charset('utf8mb4');
