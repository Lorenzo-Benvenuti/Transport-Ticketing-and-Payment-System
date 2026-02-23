<?php
// Shared web helpers (require app-level constants like ENABLE_CSRF).

if (!function_exists('abort_page')) {
  function abort_page(string $message, int $code = 400, string $title = 'Errore')
  {
    http_response_code($code);
    echo "<h1>" . htmlspecialchars($title) . "</h1>";
    echo "<p>" . htmlspecialchars($message) . "</p>";
    exit;
  }
}

if (!function_exists('redirect')) {
  function redirect(string $url)
  {
    header('Location: ' . $url);
    exit;
  }
}

if (!function_exists('money_fmt')) {
  function money_fmt($v): string
  {
    return number_format((float)$v, 2, ',', '.');
  }
}

if (!function_exists('csrf_token')) {
  function csrf_token(): string
  {
    if (!defined('ENABLE_CSRF') || !ENABLE_CSRF) return '';
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf'])) {
      $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
  }
}

if (!function_exists('csrf_check')) {
  function csrf_check(): bool
  {
    if (!defined('ENABLE_CSRF') || !ENABLE_CSRF) return true;
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
    if (!$ok) abort_page('CSRF token mancante/non valido.', 400);
    return true;
  }
}

function require_nonempty_secret(string $name, string $value): void
{
  if ($value !== '') return;
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Misconfiguration: $name is empty. Configure it in the .env file.";
  exit;
}
