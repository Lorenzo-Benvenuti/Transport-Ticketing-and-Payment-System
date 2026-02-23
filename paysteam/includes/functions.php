<?php
require_once __DIR__ . '/../../shared/security.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../../shared/web.php';

function abort_page(string $message, int $code = 400, string $title = 'Errore')
{
  http_response_code($code);
  $safeMsg = htmlspecialchars($message);
  require __DIR__ . '/header.php';
  echo "<h2>" . htmlspecialchars($title) . "</h2>";
  echo "<div class=\"alert error\">{$safeMsg}</div>";
  echo '<a class="btn" href="index.php">Home</a>';
  require __DIR__ . '/footer.php';
  exit;
}

function client_ip(): string
{
  return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function rate_limit_login_or_register(string $bucket, int $max, int $windowSeconds): void
{
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (!isset($_SESSION['rate_limit'])) $_SESSION['rate_limit'] = [];
  $key = $bucket . ':' . client_ip();
  if (!rate_limiter_allow($_SESSION['rate_limit'], $key, $max, $windowSeconds)) {
    abort_page('Troppi tentativi. Riprova più tardi.', 429, 'Rate limit');
  }
}

function q($sql, $params = [], $types = '')
{
  global $conn;
  $stmt = $conn->prepare($sql);
  if ($params) {
    if (!$types) {
      foreach ($params as $p) {
        $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
      }
    }
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  return $stmt;
}

// csrf_token(), csrf_check(), money_fmt(), redirect() are provided by shared/web.php

function require_login(string $loginUrl = 'login.php')
{
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (empty($_SESSION['user'])) {
    redirect($loginUrl);
  }
}

function require_guest()
{
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (!empty($_SESSION['user'])) {
    $u = $_SESSION['user'];
    if (!empty($u['ruolo']) && $u['ruolo'] === RUOLO_ESERCENTE) {
      redirect('esercente.php');
    } else {
      redirect('dashboard.php');
    }
  }
}

function current_user()
{
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  return $_SESSION['user'] ?? null;
}

function login($email, $password)
{
  $row = q("SELECT id_utente, email, password, ruolo, nome, cognome FROM p2_utenti WHERE email=?", [$email])->get_result()->fetch_assoc();
  if (!$row) return false;
  if (PLAIN_PASSWORDS) {
    if ($password !== $row['password']) return false;
  } else {
    if (!password_verify($password, $row['password'])) return false;
  }
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  session_regenerate_id(true);
  $_SESSION['user'] = ['id' => $row['id_utente'], 'email' => $row['email'], 'ruolo' => $row['ruolo'], 'nome' => $row['nome'], 'cognome' => $row['cognome']];
  return true;
}

function register_user($nome, $cognome, $email, $password, $ruolo)
{
  if (PLAIN_PASSWORDS) {
    $pwd = $password;
  } else {
    $pwd = password_hash($password, PASSWORD_BCRYPT);
  }
  q("INSERT INTO p2_utenti (nome,cognome,email,password,ruolo) VALUES (?,?,?,?,?)", [$nome, $cognome, $email, $pwd, $ruolo]);
  return true;
}

function require_role($ruolo)
{
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (empty($_SESSION['user']) || $_SESSION['user']['ruolo'] !== $ruolo) {
    abort_page('Accesso negato', 403);
  }
}

function api_require_bearer()
{
  // Fail-fast: do not expose API endpoints with empty token.
  require_nonempty_secret('API_BEARER_TOKEN', API_BEARER_TOKEN);

  // Raccoglie Authorization da varie fonti
  $auth = '';
  if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'];
  } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
  } elseif (function_exists('getallheaders')) {
    $h = getallheaders();
    if (isset($h['Authorization']))      $auth = $h['Authorization'];
    elseif (isset($h['authorization']))  $auth = $h['authorization'];
  }

  if (!$auth || stripos($auth, 'Bearer ') !== 0) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'missing_bearer']);
    exit;
  }

  $token = trim(substr($auth, 7));
  if (!hash_equals(API_BEARER_TOKEN, $token)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'invalid_token']);
    exit;
  }
}

function webhook_post($url, $payload)
{
  // Fail-fast: without a secret, the merchant cannot verify signatures.
  require_nonempty_secret('WEBHOOK_SECRET', WEBHOOK_SECRET);

  if (!isset($payload['ts'])) $payload['ts'] = time();
  $raw = json_encode($payload);
  $sig = hmac_sign_base64($raw, WEBHOOK_SECRET);
  $ctx = stream_context_create(['http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/json\r\nX-Signature: $sig\r\n",
    'content' => $raw,
    'timeout' => 5,
    'ignore_errors' => true
  ]]);

  $res = @file_get_contents($url, false, $ctx);
  if ($res === false) {
    error_log("[PaySteam] webhook_post failed: url={$url}");
    return false;
  }

  // Best-effort: log non-2xx responses
  if (isset($http_response_header[0]) && !preg_match('/\s2\d\d\s/', $http_response_header[0])) {
    error_log("[PaySteam] webhook_post non-2xx: {$http_response_header[0]} url={$url}");
  }

  return true;
}
