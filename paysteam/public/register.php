<?php
require_once __DIR__ . '/../includes/functions.php';
require_guest();
require_once __DIR__ . '/../includes/header.php';

$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  rate_limit_login_or_register('register', LOGIN_RATE_LIMIT_MAX, LOGIN_RATE_LIMIT_WINDOW);
  $nome = trim($_POST['nome'] ?? '');
  $cognome = trim($_POST['cognome'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass = trim($_POST['password'] ?? '');
  $ruolo = trim($_POST['ruolo'] ?? RUOLO_CONSUMATORE);
  if (!$nome || !$cognome || !$email || !$pass) {
    $err = 'Compila tutti i campi.';
  } elseif (!validate_email($email)) {
    $err = 'Email non valida.';
  } elseif (!validate_password($pass)) {
    $err = 'Password troppo corta (minimo 8 caratteri).';
  } else {
    try {
      register_user($nome, $cognome, $email, $pass, $ruolo);
      // Crea conto con saldo iniziale di 50 eur
      $uid = q("SELECT id_utente FROM p2_utenti WHERE email=?", [$email])->get_result()->fetch_assoc()['id_utente'];
      q("INSERT INTO p2_conti (id_utente,saldo) VALUES (?,50.00)", [(int)$uid], 'i');
      $msg = 'Registrazione completata. Ora puoi accedere.';
    } catch (Throwable $e) {
      $err = 'Registrazione non riuscita (email già esistente?).';
    }
  }
}
?>

<h2>Registrazione</h2>
<?php if ($msg): ?><div class="alert success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert error"><?= $err ?></div><?php endif; ?>
<form method="post">
  <?php if (ENABLE_CSRF): ?><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><?php endif; ?>
  <div class="grid2">
    <label>Nome <input name="nome" required></label>
    <label>Cognome <input name="cognome" required></label>
  </div>
  <label>Email <input type="email" name="email" required></label>
  <label>Password <input type="password" name="password" required></label>
  <label>Ruolo
    <select name="ruolo">
      <option value="<?= RUOLO_CONSUMATORE ?>">Consumatore</option>
      <option value="<?= RUOLO_ESERCENTE ?>">Esercente</option>
    </select>
  </label>
  <button class="btn">Crea account</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>