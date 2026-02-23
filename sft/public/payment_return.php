<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

login_required();

$tx = (string)($_GET['tx'] ?? '');
$statusCb = (string)($_GET['status'] ?? '');
$receipt = null;

if ($tx === '') {
  abort_page('Transazione non valida.', 400, 'Pagamento');
}

if (PAYSTEAM_API_TOKEN === '') {
  abort_page('Configurazione mancante: PAYSTEAM_API_TOKEN. Controlla il file .env', 500, 'Configurazione');
}

// Chiede stato a PaySteam (protetto da Bearer)
$ctx = stream_context_create(['http' => [
  'method'  => 'GET',
  'header'  => "Authorization: Bearer " . PAYSTEAM_API_TOKEN . "\r\n",
  'timeout' => 5
]]);

$result = @file_get_contents(PAYSTEAM_BASE_URL . '/public/api/result.php?tx=' . urlencode($tx), false, $ctx);
if ($result) {
  $receipt = json_decode($result, true);
}
if (!$receipt) {
  abort_page('Impossibile recuperare la ricevuta.', 502, 'Pagamento');
}

// Hardening: la ricevuta deve riferirsi a un biglietto dell'utente loggato
$ext = (string)($receipt['external_tx_id'] ?? '');
if (!preg_match('/^biglietto-(\d+)/', $ext, $m)) {
  abort_page('Riferimento transazione non valido.', 422, 'Pagamento');
}

$bid = (int)$m[1];
$uid = (int)$_SESSION['user']['id'];

$row = q(
  "SELECT id_biglietto, id_corsa, prezzo, pagato
   FROM p1_biglietti
   WHERE id_biglietto=? AND id_utente=?",
  [$bid, $uid],
  'ii'
)->get_result()->fetch_assoc();

if (!$row) {
  abort_page('Questa ricevuta non appartiene al tuo account.', 403, 'Accesso negato');
}

/*
|--------------------------------------------------------------------------
| Fallback: se PaySteam dice SUCCEEDED,
| chiude il pagamento anche se il webhook non è arrivato
|--------------------------------------------------------------------------
*/
if (($receipt['status'] ?? '') === 'SUCCEEDED' && (int)$row['pagato'] === 0) {
  $amount   = (float)($receipt['amount'] ?? 0);
  $expected = (float)$row['prezzo'];

  if (abs($amount - $expected) < 0.01) {
    $providerRef = (string)($receipt['tx_token'] ?? $tx);

    q(
      "INSERT INTO p1_pagamenti (id_corsa, importo, valuta, provider, provider_ref, stato, created_at, updated_at)
       VALUES (?, ?, 'EUR', 'PaySteam', ?, 'SUCCEEDED', NOW(), NOW())
       ON DUPLICATE KEY UPDATE stato='SUCCEEDED', importo=VALUES(importo), updated_at=NOW()",
      [(int)$row['id_corsa'], $amount, $providerRef],
      'ids'
    );

    q("UPDATE p1_biglietti SET pagato=1 WHERE id_biglietto=?", [$bid], 'i');
  }
}

require_once __DIR__ . '/../includes/header.php';
?>

<h2>Ricevuta pagamento</h2>
<div class="card">
  <p><strong>Transazione:</strong> <?= htmlspecialchars($tx ?: 'N/D') ?></p>
  <p><strong>Stato callback:</strong> <?= htmlspecialchars($statusCb ?: 'N/D') ?></p>
  <p><strong>Stato provider:</strong> <?= htmlspecialchars((string)($receipt['status'] ?? 'N/D')) ?></p>
  <p><strong>Riferimento:</strong> <?= htmlspecialchars((string)($receipt['external_tx_id'] ?? 'N/D')) ?></p>
  <p><strong>Importo:</strong> <?= htmlspecialchars(number_format((float)($receipt['amount'] ?? 0), 2)) ?> <?= htmlspecialchars((string)($receipt['currency'] ?? 'EUR')) ?></p>
</div>

<a class="btn" href="index.php">Torna alla home</a>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>