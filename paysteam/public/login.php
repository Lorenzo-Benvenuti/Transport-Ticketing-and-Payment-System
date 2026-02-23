<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$err = '';
$isAreaRiservata = isset($_GET['area']) && $_GET['area'] == '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_check();
    rate_limit_login_or_register('login', LOGIN_RATE_LIMIT_MAX, LOGIN_RATE_LIMIT_WINDOW);

    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) {
        $err = "Inserisci email e password.";
    } elseif (!validate_email($email)) {
        $err = "Email non valida.";
    } else {

        $stmt = q(
            "SELECT id_utente, email, password, ruolo, nome 
             FROM p2_utenti 
             WHERE email=?",
            [$email],
            's'
        );

        $u = $stmt->get_result()->fetch_assoc();

        $passwordOk = false;

        if ($u) {
            if (PLAIN_PASSWORDS) {
                $passwordOk = ($pass === $u['password']);
            } else {
                $passwordOk = password_verify($pass, $u['password']);
            }
        }

        if (!$u || !$passwordOk) {
            $err = "Email o password non valide.";
        } else {

            $isEsercente   = ($u['ruolo'] === RUOLO_ESERCENTE);
            $isConsumatore = ($u['ruolo'] === RUOLO_CONSUMATORE);

            if ($isAreaRiservata && $isConsumatore) {
                $err = "Questa è l'Area Riservata: accedono solo gli esercenti.";
            } elseif (!$isAreaRiservata && $isEsercente) {
                $err = "Questo login è per consumatori: usa 'Area Riservata' per accedere come esercente.";
            } else {

                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id'    => (int)$u['id_utente'],
                    'email' => $u['email'],
                    'nome'  => $u['nome'],
                    'ruolo' => $u['ruolo'],
                ];

                if ($isEsercente) {
                    header('Location: esercente.php');
                    exit;
                }

                header('Location: dashboard.php');
                exit;
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<h2><?= $isAreaRiservata ? "Login Staff Area" : "Login" ?></h2>
<p style="margin-top:-8px;">
  <?php if ($isAreaRiservata): ?>
    Sei un consumatore? <a href="login.php">Vai al login consumatori</a>
  <?php else: ?>
    Sei staff/merchant? <a href="login.php?area=1">Vai alla Staff Area</a>
  <?php endif; ?>
</p>
<?php if ($err): ?>
    <div class="alert error"><?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<form method="post" class="form">
    <?php if (ENABLE_CSRF): ?>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php endif; ?>

    <label>Email
        <input type="email" name="email" required>
    </label>

    <label>Password
        <input type="password" name="password" required>
    </label>

    <button type="submit" class="btn">Accedi</button>
</form>

<p>Non hai un account? <a href="register.php">Registrati</a></p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>