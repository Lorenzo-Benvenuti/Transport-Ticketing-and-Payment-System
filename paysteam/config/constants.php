<?php
require_once __DIR__ . '/../../shared/env.php';
load_env(__DIR__ . '/../.env');

define('APP_NAME', 'PaySteam');
define('APP_BASE_URL', getenv('APP_BASE_URL') ?: 'http://localhost:8080/paysteam');

define('PLAIN_PASSWORDS', false);

// User roles
define('RUOLO_CONSUMATORE', 'consumatore');
define('RUOLO_ESERCENTE', 'esercente');

define('ENABLE_CSRF', true);

// Basic protections (demo-grade)
define('LOGIN_RATE_LIMIT_MAX', 5);
define('LOGIN_RATE_LIMIT_WINDOW', 600); // seconds
define('WEBHOOK_MAX_SKEW', 600); // seconds (merchant may reject older webhooks)

// API: chiave bearer per chiamate M2M (merchant -> provider)
define('API_BEARER_TOKEN', getenv('API_BEARER_TOKEN') ?: '');

// Webhook firma HMAC per notifiche al merchant
define('WEBHOOK_SECRET', getenv('WEBHOOK_SECRET') ?: '');

// Valuta
define('CURRENCY', getenv('CURRENCY') ?: 'EUR');
