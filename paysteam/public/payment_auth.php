<?php
require_once __DIR__ . '/../includes/functions.php';

require_login();
$u = current_user();

// Parametri transazione
$tx = $_GET['tx'] ?? '';
if (!$tx) {
  abort_page('Transazione mancante', 400, 'Pagamento');
}
$tr = q("SELECT * FROM p2_transazioni WHERE tx_token=?", [$tx])->get_result()->fetch_assoc();
if (!$tr) {
  abort_page('Transazione non trovata', 404, 'Pagamento');
}

// Solo il consumatore assegnato può autorizzare
if ((int)$tr['id_consumatore'] !== (int)$u['id']) {
  http_response_code(403);
  abort_page('Non sei il titolare della transazione.', 403, 'Pagamento');
}

$err = '';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? 'authorize';
  if (($tr['stato'] ?? '') !== 'PENDING') {
    $err = 'Transazione non in stato PENDING.';
  } elseif ($action === 'cancel') {
    // Cancel requested by user
    q("UPDATE p2_transazioni SET stato='CANCELLED', updated_at=NOW() WHERE id_transazione=?", [(int)$tr['id_transazione']], 'i');
    // Best-effort notify merchant
    webhook_post($tr['webhook_url'], [
      'external_tx_id' => $tr['external_tx_id'],
      'status' => 'CANCELLED',
      'amount' => (float)$tr['importo'],
      'paysteam_tx_token' => $tx,
      'paysteam_id_transazione' => (int)$tr['id_transazione']
    ]);
    $ret = $tr['return_url'] . (strpos($tr['return_url'], '?') === false ? '?' : '&') . 'tx=' . urlencode($tx) . '&status=KO&reason=cancelled';
    header("Location: $ret");
    exit;
  } else {
    // Verifica saldo consumatore
    $saldo = q("SELECT saldo FROM p2_conti WHERE id_utente=?", [(int)$u['id']], 'i')->get_result()->fetch_assoc()['saldo'];
    if ($saldo < (float)$tr['importo']) {
      // Keep it actionable without sounding like a real bank.
      $err = 'Saldo insufficiente. Ricarica il wallet o annulla.';
    } else {
      // Operazioni atomiche: evita stati inconsistenti in caso di errore
      global $conn;
      $conn->begin_transaction();
      try {
        // Addebita al consumatore
        q("UPDATE p2_conti SET saldo=saldo-? WHERE id_utente=?", [(float)$tr['importo'], (int)$u['id']], 'di');
        q(
          "INSERT INTO p2_movimenti (id_utente,data_mov,descrizione,importo,verso) VALUES (?,?,?,?,'USCITA')",
          [(int)$u['id'], date('Y-m-d H:i:s'), 'Pagamento: ' . $tr['descrizione'], (float)$tr['importo']],
          'issd'
        );
        // Accredita all'esercente
        q("UPDATE p2_conti SET saldo=saldo+? WHERE id_utente=?", [(float)$tr['importo'], (int)$tr['id_esercente']], 'di');
        q(
          "INSERT INTO p2_movimenti (id_utente,data_mov,descrizione,importo,verso) VALUES (?,?,?,?,'ENTRATA')",
          [(int)$tr['id_esercente'], date('Y-m-d H:i:s'), 'Incasso: ' . $tr['descrizione'], (float)$tr['importo']],
          'issd'
        );
        // Chiude transazione
        q("UPDATE p2_transazioni SET stato='SUCCEEDED', updated_at=NOW() WHERE id_transazione=?", [(int)$tr['id_transazione']], 'i');
        $conn->commit();
      } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
      }

      $msg = 'Pagamento effettuato con successo.';
      // Notifica il merchant (post-commit) con un ref univoco per idempotenza lato merchant
      webhook_post($tr['webhook_url'], [
        'external_tx_id' => $tr['external_tx_id'],
        'status' => 'OK',
        'amount' => (float)$tr['importo'],
        'paysteam_tx_token' => $tx,
        'paysteam_id_transazione' => (int)$tr['id_transazione']
      ]);
      // Redirect al merchant return_url con tx e status=OK
      $ret = $tr['return_url'] . (strpos($tr['return_url'], '?') === false ? '?' : '&') . 'tx=' . urlencode($tx) . '&status=OK';
      header("Location: $ret");
      exit;
    }
  }
  // Ricarica record aggiornato
  $tr = q("SELECT * FROM p2_transazioni WHERE tx_token=?", [$tx])->get_result()->fetch_assoc();
}
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h2>Autorizzazione Pagamento</h2>
<div class="card">
  <p><strong>Esercente:</strong> #<?= $tr['id_esercente'] ?></p>
  <p><strong>Descrizione:</strong> <?= htmlspecialchars($tr['descrizione']) ?></p>
  <p><strong>Importo:</strong> € <?= money_fmt($tr['importo']) ?> <?= CURRENCY ?></p>
  <p><strong>Stato:</strong> <?= htmlspecialchars($tr['stato']) ?></p>
</div>
<?php if ($msg): ?><div class="alert success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert error"><?= $err ?></div><?php endif; ?>
<?php if ($tr['stato'] === 'PENDING'): ?>
  <form method="post">
    <?php if (ENABLE_CSRF): ?><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><?php endif; ?>
    <button class="btn primary" name="action" value="authorize">Autorizza pagamento</button>
    <button class="btn" name="action" value="cancel" formnovalidate>Annulla</button>
  </form>
<?php else: ?>
  <a class="btn" href="dashboard.php">Torna alla dashboard</a>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>