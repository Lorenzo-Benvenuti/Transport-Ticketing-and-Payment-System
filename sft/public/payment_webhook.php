<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

// Fail-fast: without a secret, HMAC verification is meaningless.
require_nonempty_secret('PAYSTEAM_WEBHOOK_SECRET', PAYSTEAM_WEBHOOK_SECRET);

// Verifica firma HMAC
$raw = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
if (!$sig || !hmac_verify_base64($raw, $sig, PAYSTEAM_WEBHOOK_SECRET)) {
  http_response_code(401);
  echo json_encode(['error' => 'invalid_signature']);
  exit;
}

$payload = json_decode($raw, true);
if (!$payload) {
  http_response_code(400);
  echo json_encode(['error' => 'invalid_json']);
  exit;
}

// Basic replay protection (optional but useful for a demo): reject very old webhooks.
$ts = (int)($payload['ts'] ?? 0);
if ($ts > 0) {
  $skew = abs(time() - $ts);
  if ($skew > PAYSTEAM_WEBHOOK_MAX_SKEW) {
    http_response_code(400);
    echo json_encode(['error' => 'stale_webhook']);
    exit;
  }
}

$ext = $payload['external_tx_id'] ?? '';
$status = strtoupper($payload['status'] ?? '');
$amount = (float)($payload['amount'] ?? 0);

// Riferimento univoco lato provider per idempotenza (PaySteam invia tx token/id)
$providerRef = (string)($payload['paysteam_tx_token'] ?? $payload['tx_token'] ?? $payload['paysteam_tx'] ?? '');


// Marca biglietto pagato se status OK ed external_tx_id in formato biglietto-<id>
// Normalizza lo status una sola volta
$statusNorm = strtoupper((string)$status);

// Validazione minima
if (!$ext) {
  http_response_code(422);
  echo json_encode(['error' => 'missing_external_tx_id']);
  exit;
}

/** Registra (o aggiorna) pagamento locale in modo consistente:
* - id_corsa NON NULL (FK)
* - idempotente: UNIQUE(provider, provider_ref)
* - marca il biglietto pagato SOLO se importo atteso combacia
*/
if (preg_match('/^biglietto-(\d+)/', $ext, $m)) {
  $bid = (int)$m[1];

  $t = q("SELECT id_corsa, prezzo, pagato FROM p1_biglietti WHERE id_biglietto=?", [$bid], 'i')->get_result()->fetch_assoc();
  if ($t) {
    $expected = (float)$t['prezzo'];
    $amountOk = abs($amount - $expected) < 0.01;

    // Se PaySteam non invia un ref univoco, ripiega su external id (meno robusto, ma evita NULL)
    if ($providerRef === '') {
      $providerRef = $ext;
    }

    if ($statusNorm === 'OK' && $amountOk) {
      $localState = 'SUCCEEDED';
    } elseif (in_array($statusNorm, ['CANCELLED', 'DENIED'], true)) {
      $localState = 'CANCELLED';
    } else {
      $localState = 'FAILED';
    }

    q(
      "INSERT INTO p1_pagamenti (id_corsa, importo, valuta, provider, provider_ref, stato, created_at, updated_at)
       VALUES (?, ?, 'EUR', 'PaySteam', ?, ?, NOW(), NOW())
       ON DUPLICATE KEY UPDATE stato=VALUES(stato), importo=VALUES(importo), updated_at=NOW()",
      [
        (int)$t['id_corsa'],
        (float)$amount,
        $providerRef,
        $localState
      ],
      'idss'
    );

    // Solo qui: status OK + importo combaciante
    if ($localState === 'SUCCEEDED') {
      q("UPDATE p1_biglietti SET pagato=1 WHERE id_biglietto=?", [$bid], 'i');
    }
  }
}

echo json_encode(['ok' => true]);
