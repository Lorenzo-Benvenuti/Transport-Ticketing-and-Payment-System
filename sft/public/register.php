<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';

$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  rate_limit_login_or_register('register', LOGIN_RATE_LIMIT_MAX, LOGIN_RATE_LIMIT_WINDOW);

  $nome = trim((isset($_POST['nome']) ? $_POST['nome'] : ''));
  $cognome = trim((isset($_POST['cognome']) ? $_POST['cognome'] : ''));
  $email = trim((isset($_POST['email']) ? $_POST['email'] : ''));
  $pass  = (isset($_POST['password']) ? $_POST['password'] : '');
  if ($nome && $cognome && $email && $pass) {
    if (!validate_email($email)) {
      $err = 'Email non valida.';
    } elseif (!validate_password($pass)) {
      $err = 'Password troppo corta (minimo 8 caratteri).';
    } else {
    $hash = (defined('PLAIN_PASSWORDS') && PLAIN_PASSWORDS)
      ? $pass
      : password_hash($pass, PASSWORD_DEFAULT);
    try {
      q(
        "INSERT INTO p1_utenti (nome,cognome,email,password,ruolo) VALUES (?,?,?,?,?)",
        [$nome, $cognome, $email, $hash, RUOLO_UTENTE],
        'sssss'
      );
      $msg = "Registrazione completata. Ora effettua il login.";
    } catch (Throwable $e) {
      $err = "Errore registrazione: email già in uso?";
    }
    }
  } else {
    $err = "Compila tutti i campi";
  }
}
?>

<h2>Registrazione</h2>
<?php if ($msg): ?><div class="alert success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert error"><?= $err ?></div><?php endif; ?>
<form method="post" class="form">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

  <label>Nome <input type="text" name="nome" required></label>
  <label>Cognome <input type="text" name="cognome" required></label>
  <label>Email <input type="email" name="email" required></label>
  <label>Password <input type="password" name="password" required></label>
  <button type="submit" class="btn">Registrati</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>