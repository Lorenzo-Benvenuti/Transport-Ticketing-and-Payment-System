<?php
require_once __DIR__ . '/../config/constants.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  session_start();
}

function login_required()
{
  if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
  }
}

function role_required($role)
{
  login_required();
  if ($_SESSION['user']['ruolo'] !== $role) {
    http_response_code(403);
    echo "Accesso negato";
    exit;
  }
}

function set_user_session($row)
{
  $_SESSION['user'] = [
    'id' => (int)$row['id_utente'],
    'email' => $row['email'],
    'nome' => $row['nome'],
    'ruolo' => $row['ruolo']
  ];
}

function logout()
{
  $_SESSION = [];
  session_destroy();
  header('Location: index.php');
  exit;
}

function is_backoffice_role(): bool
{
  return !empty($_SESSION['user'])
    && in_array($_SESSION['user']['ruolo'], [RUOLO_ADMIN, RUOLO_ESERCIZIO], true);
}

function redirect_front_to_backoffice_if_needed(): void
{
  if (!is_backoffice_role()) return;
  $role = $_SESSION['user']['ruolo'];
  $target = ($role === RUOLO_ADMIN) ? 'backoffice_admin.php' : 'backoffice_esercizio.php';
  header('Location: ' . $target);
  exit;
}
