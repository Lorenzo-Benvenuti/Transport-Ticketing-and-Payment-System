<?php
require_once __DIR__ . '/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Session cookie hardening (basic)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,        // until browser closes
        'path' => '/',
        'secure' => $isHttps,   // true only if HTTPS
        'httponly' => true,     // JS cannot access the cookie
        'samesite' => 'Lax',    // helps mitigate CSRF
    ]);

    session_start();
}

if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = null;
}
