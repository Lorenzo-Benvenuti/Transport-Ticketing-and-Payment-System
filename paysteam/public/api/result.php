<?php
// GET /public/api/result.php?tx=...
// Endpoint M2M protetto da Bearer: restituisce un riepilogo della transazione (utile al merchant per mostrare una ricevuta coerente).

require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

api_require_bearer();

$tx = $_GET['tx'] ?? '';
if (!$tx) {
  http_response_code(400);
  echo json_encode(['error' => 'missing_tx']);
  exit;
}

$r = q(
  "SELECT stato, external_tx_id, importo, tx_token
   FROM p2_transazioni
   WHERE tx_token=?",
  [$tx],
  's'
)->get_result()->fetch_assoc();

if (!$r) {
  http_response_code(404);
  echo json_encode(['error' => 'not_found']);
  exit;
}

echo json_encode([
  'status' => $r['stato'],
  'external_tx_id' => $r['external_tx_id'],
  'amount' => (float)$r['importo'],
  'currency' => CURRENCY,
  'tx_token' => $r['tx_token']
]);
