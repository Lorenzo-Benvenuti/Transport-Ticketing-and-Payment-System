<?php
require_once __DIR__ . '/../../shared/env.php';
load_env(__DIR__ . '/../.env');

define('APP_NAME', 'Sistema Ferroviario Turistico');
define('APP_VERSION', '1.0');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '');

// Base URLs (PUBLIC = browser/host, INTERNAL = server-to-server inside Docker)
define('PUBLIC_BASE_URL', getenv('PUBLIC_BASE_URL') ?: 'http://localhost:8080/sft');
define('INTERNAL_BASE_URL', getenv('INTERNAL_BASE_URL') ?: 'http://localhost/sft');

define('TARIFFA_KM', 0.20);
define('VEL_MEDIA_DEFAULT', 50.0);

define('RUOLO_UTENTE', 'passeggero');
define('RUOLO_ADMIN', 'amministrazione');
define('RUOLO_ESERCIZIO', 'esercizio');

define('PLAIN_PASSWORDS', false);

// Sicurezza
define('ENABLE_CSRF', true);

// Basic protections (demo-grade)
define('LOGIN_RATE_LIMIT_MAX', 5);
define('LOGIN_RATE_LIMIT_WINDOW', 600); // seconds

// Integrazione PaySteam (opzionale / demo)
// Default coerente con XAMPP e con i container (server-to-server): http://localhost/paysteam
define('PAYSTEAM_BASE_URL', getenv('PAYSTEAM_BASE_URL') ?: 'http://localhost/paysteam');
define('PAYSTEAM_API_TOKEN', getenv('PAYSTEAM_API_TOKEN') ?: '');
define('PAYSTEAM_WEBHOOK_SECRET', getenv('PAYSTEAM_WEBHOOK_SECRET') ?: '');
define('PAYSTEAM_WEBHOOK_MAX_SKEW', 600); // seconds

// Merchant identity on PaySteam (used to create transactions). Prefer email to avoid cross-DB ID coupling.
define('PAYSTEAM_MERCHANT_EMAIL', getenv('PAYSTEAM_MERCHANT_EMAIL') ?: 'esercente@paysteam.it');
