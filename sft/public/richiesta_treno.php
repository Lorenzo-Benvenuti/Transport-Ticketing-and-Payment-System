<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

role_required(RUOLO_ADMIN);

require_once __DIR__ . '/../includes/header.php';

$err = $ok = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

    $msg = trim($_POST['messaggio']);
    if ($msg === '') {
        $err = "Scrivi le indicazioni.";
    } else {
        q(
            "INSERT INTO p1_richieste_treni (id_admin,messaggio) VALUES (?,?)",
            [$_SESSION['user']['id'], $msg],
            'is'
        );
        $ok = "Richiesta inviata al Responsabile.";
    }
}
?>

<section>
    <h2>Richiesta treno straordinario</h2>
    <?php if ($err): ?><div class="alert error"><?= $err ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert success"><?= $ok ?></div><?php endif; ?>
    <form method="post" class="form">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

        <label>Indicazioni per il Responsabile
            <textarea name="messaggio" required></textarea>
        </label>
        <button class="btn">Invia</button>
        <br><br>
        <a href="backoffice_admin.php" class="btn" style="background:#777;">Torna al Backoffice</a>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>