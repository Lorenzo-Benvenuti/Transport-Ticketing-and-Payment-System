<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// IMPORTANT: avvia la sessione prima di qualsiasi output HTML (header/footer)
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$err = '';

$isAreaRiservata = isset($_GET['area']) && $_GET['area'] == '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  rate_limit_login_or_register('login', LOGIN_RATE_LIMIT_MAX, LOGIN_RATE_LIMIT_WINDOW);

  $email = trim((isset($_POST['email']) ? $_POST['email'] : ''));
  $pass  = (isset($_POST['password']) ? $_POST['password'] : '');

  if ($email && $pass) {
    if (!validate_email($email)) {
      $err = 'Email non valida.';
    } else {
      // Recupero utente
      $stmt = q("SELECT * FROM p1_utenti WHERE email=?", [$email], 's');
      $u = $stmt->get_result()->fetch_assoc();

      // Verifica credenziali (plain o password_hash)
      $passwordOk = false;
      if ($u) {
        if (PLAIN_PASSWORDS) {
          $passwordOk = ($pass === $u['password']);
        } else {
          $passwordOk = password_verify($pass, $u['password']);
        }
      }

      if (!$u || !$passwordOk) {
        // Credenziali sbagliate -> warning
        $err = "Email o password non valide.";
      } else {
        // Regole di coerenza tra pagina di login e ruolo
        $isAdmin     = ($u['ruolo'] === RUOLO_ADMIN);
        $isEsercizio = ($u['ruolo'] === RUOLO_ESERCIZIO);
        $isUtente    = ($u['ruolo'] === RUOLO_UTENTE);

        // Se sto usando "Area Riservata" (?area=1) ma sono un utente normale -> warning
        if ($isAreaRiservata && $isUtente) {
          $err = "Questa è l'Area Riservata: accedono solo Amministrazione/Esercizio.";
        }
        // Se sto usando il login normale ma sono admin/esercizio -> warning
        elseif (!$isAreaRiservata && ($isAdmin || $isEsercizio)) {
          $err = "Questo login è per passeggeri: usa 'Area Riservata' per accedere al backoffice.";
        }
        // Redirect nella giusta pagina in base alla sessione
        else {
          // Rigenera l'ID di sessione prima di fare redirect (anti session fixation)
          session_regenerate_id(true);
          $_SESSION['user'] = [
            'id'    => (int)$u['id_utente'],
            'email' => $u['email'],
            'nome'  => $u['nome'],
            'ruolo' => $u['ruolo'],
          ];

          if ($isAdmin) {
            header('Location: backoffice_admin.php');
            exit;
          }
          if ($isEsercizio) {
            header('Location: backoffice_esercizio.php');
            exit;
          }
          header('Location: index.php');
          exit;
        }
      }
    }
  } else {
    $err = "Inserisci email e password.";
  }
}

$title = $isAreaRiservata ? "Login Area Riservata" : "Login";
require_once __DIR__ . '/../includes/header.php';
?>
<h2><?= $title ?></h2>
<?php if ($err): ?><div class="alert error"><?= $err ?></div><?php endif; ?>
<form method="post" class="form">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

  <label>Email <input type="email" name="email" required></label>
  <label>Password <input type="password" name="password" required></label>
  <button type="submit" class="btn">Accedi</button>
</form>
<p>Non hai un account? <a href="register.php">Registrati</a></p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
