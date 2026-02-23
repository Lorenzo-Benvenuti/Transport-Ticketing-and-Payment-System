<?php
require_once __DIR__ . '/../../shared/env.php';
load_env(__DIR__ . '/../.env');

// Configurazione Database
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME') ?: '';

// Fail-fast su errori MySQLi (evita stati inconsistenti e bug silenziosi)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($host, $user, $pass, $name);
$conn->set_charset('utf8mb4');
