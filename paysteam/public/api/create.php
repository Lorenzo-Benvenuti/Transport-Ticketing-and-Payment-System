<?php
// POST /public/api/create.php

/**  Supporta input JSON o form.
  * Parametri richiesti:
  * - return_url      (dove reindirizzare il browser dopo conferma)
  * - webhook_url     (dove notificare l'esito via POST firmato)
  * - external_tx_id  (id transazione lato merchant)
  * - descrizione
  * - importo (EUR)
  *
  * Identità utenti (sceglierne UNA per parte):
  * - merchant_email  (consigliato) oppure id_esercente
  * - consumer_email  (consigliato) oppure id_consumatore
  * Nota: usare le email evita l'accoppiamento sugli ID tra DB diversi.
*/

require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

api_require_bearer();

$in = json_decode(file_get_contents('php://input'), true);
if (!$in) {
  $in = $_POST;
}

$required = ['return_url', 'webhook_url', 'external_tx_id', 'descrizione', 'importo'];
foreach ($required as $k) {
  if (!isset($in[$k]) || $in[$k] === '') {
    http_response_code(422);
    echo json_encode(['error' => 'missing_' . $k]);
    exit;
  }
}

// Resolve merchant
$id_esercente = 0;
if (!empty($in['merchant_email'])) {
  $email = trim((string)$in['merchant_email']);
  $row = q("SELECT id_utente, ruolo FROM p2_utenti WHERE email=?", [$email], 's')->get_result()->fetch_assoc();
  if (!$row || $row['ruolo'] !== RUOLO_ESERCENTE) {
    http_response_code(404);
    echo json_encode(['error' => 'merchant_not_found']);
    exit;
  }
  $id_esercente = (int)$row['id_utente'];
} elseif (isset($in['id_esercente']) && $in['id_esercente'] !== '') {
  $id_esercente = (int)$in['id_esercente'];
  $row = q("SELECT id_utente, ruolo FROM p2_utenti WHERE id_utente=?", [$id_esercente], 'i')->get_result()->fetch_assoc();
  if (!$row || $row['ruolo'] !== RUOLO_ESERCENTE) {
    http_response_code(404);
    echo json_encode(['error' => 'merchant_not_found']);
    exit;
  }
} else {
  http_response_code(422);
  echo json_encode(['error' => 'missing_merchant_identity']);
  exit;
}

// Resolve consumer
$id_consumatore = 0;
if (!empty($in['consumer_email'])) {
  $email = trim((string)$in['consumer_email']);
  $row = q("SELECT id_utente, ruolo FROM p2_utenti WHERE email=?", [$email], 's')->get_result()->fetch_assoc();
  if (!$row || $row['ruolo'] !== RUOLO_CONSUMATORE) {
    http_response_code(404);
    echo json_encode(['error' => 'consumer_not_found']);
    exit;
  }
  $id_consumatore = (int)$row['id_utente'];
} elseif (isset($in['id_consumatore']) && $in['id_consumatore'] !== '') {
  $id_consumatore = (int)$in['id_consumatore'];
  $row = q("SELECT id_utente, ruolo FROM p2_utenti WHERE id_utente=?", [$id_consumatore], 'i')->get_result()->fetch_assoc();
  if (!$row || $row['ruolo'] !== RUOLO_CONSUMATORE) {
    http_response_code(404);
    echo json_encode(['error' => 'consumer_not_found']);
    exit;
  }
} else {
  http_response_code(422);
  echo json_encode(['error' => 'missing_consumer_identity']);
  exit;
}

$importo = (float)$in['importo'];
if ($importo <= 0) {
  http_response_code(422);
  echo json_encode(['error' => 'invalid_importo']);
  exit;
}

$token = bin2hex(random_bytes(12));
q(
  "INSERT INTO p2_transazioni (id_esercente,id_consumatore,external_tx_id,descrizione,importo,return_url,webhook_url,tx_token,stato,created_at,updated_at)
   VALUES (?,?,?,?,?,?,?,?,'PENDING',NOW(),NOW())",
  [$id_esercente, $id_consumatore, (string)$in['external_tx_id'], (string)$in['descrizione'], $importo, (string)$in['return_url'], (string)$in['webhook_url'], $token],
  'iissdsss'
);

$auth_url = APP_BASE_URL . '/public/payment_auth.php?tx=' . $token;

echo json_encode([
  'auth_url' => $auth_url,
  'tx_token' => $token,
  'status' => 'PENDING'
]);
