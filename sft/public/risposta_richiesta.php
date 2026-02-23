<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

role_required(RUOLO_ESERCIZIO);

require_once __DIR__ . '/../includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$err = $ok = "";

if ($id <= 0) {
  echo "<div class='alert error'>Richiesta non valida.</div>";
  require_once __DIR__ . '/../includes/footer.php';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $resp = trim((isset($_POST['risposta']) ? $_POST['risposta'] : ''));
  $stato = (isset($_POST['stato']) ? $_POST['stato'] : '');
  if ($resp === '' || ($stato !== 'Approvata' && $stato !== 'Rifiutata')) {
    $err = "Compila tutti i campi.";
  } else {
    q("UPDATE p1_richieste_treni SET risposta=?, stato=? WHERE id_richiesta=?", [$resp, $stato, $id], 'ssi');
    $ok = "Risposta inviata.";
  }
}

$res = q("SELECT * FROM p1_richieste_treni WHERE id_richiesta=?", [$id], 'i')->get_result()->fetch_assoc();
if (!$res) {
  echo "<div class='alert error'>Richiesta non trovata.</div>";
  require_once __DIR__ . '/../includes/footer.php';
  exit;
}
?>
<h2>Risposta richiesta straordinaria</h2>

<p><strong>Messaggio:</strong> <?= htmlspecialchars($res['messaggio']) ?></p>

<?php if ($err): ?><div class="alert error"><?= $err ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert success"><?= $ok ?></div><?php endif; ?>

<form method="post" class="form">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

  <label>Risposta
    <textarea name="risposta" required><?= isset($res['risposta']) ? htmlspecialchars($res['risposta']) : '' ?></textarea>
  </label>
  <label>Esito
    <select name="stato" required>
      <?php $current = $res['stato']; ?>
      <option value="Approvata" <?= $current === 'Approvata' ? 'selected' : '' ?>>Approvata</option>
      <option value="Rifiutata" <?= $current === 'Rifiutata' ? 'selected' : '' ?>>Rifiutata</option>
    </select>
  </label>
  <button class="btn">Invia</button>
  <a class="btn" href="backoffice_esercizio.php" class="btn" style="background:#777;">Torna al Backoffice</a>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>