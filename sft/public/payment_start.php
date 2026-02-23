<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../shared/http.php';
login_required();

// Fail-fast: do not run the demo payment integration with empty secrets.
require_nonempty_secret('PAYSTEAM_API_TOKEN', PAYSTEAM_API_TOKEN);

$id_biglietto = isset($_GET['id_biglietto']) ? (int)$_GET['id_biglietto'] : 0;
if ($id_biglietto <= 0) {
  abort_page('Seleziona un biglietto valido.', 400, 'Pagamento');
}

$b = q("SELECT b.id_biglietto, b.id_corsa, b.prezzo, COALESCE(b.pagato,0) AS pagato, b.id_utente
        FROM p1_biglietti b WHERE b.id_biglietto=?", [$id_biglietto], 'i')->get_result()->fetch_assoc();
if (!$b) {
  abort_page('Biglietto non trovato.', 404, 'Pagamento');
}
$uid = (int)$_SESSION['user']['id'];
if ((int)$b['id_utente'] !== $uid) {
  abort_page('Non puoi pagare un biglietto che non è tuo.', 403, 'Pagamento');
}
if ((int)$b['pagato'] === 1) {
  abort_page('Questo biglietto risulta già pagato.', 400, 'Pagamento');
}

// Verifica che la corsa non sia annullata e non sia scaduta
$st = q("SELECT c.id_corsa
         FROM p1_corse c
         JOIN p1_biglietti b ON b.id_corsa=c.id_corsa
         WHERE b.id_biglietto=? AND c.cancellata=0 AND c.data>=CURDATE()", [$id_biglietto], 'i');
$ok = $st->get_result()->fetch_assoc();
if (!$ok) {
  abort_page('Impossibile pagare: corsa annullata o non più valida.', 400, 'Pagamento');
}

$id_corsa = (int)$b['id_corsa'];
$importo  = (float)$b['prezzo'];

$merchant_email = PAYSTEAM_MERCHANT_EMAIL;
$consumer_email  = (string)($_SESSION['user']['email'] ?? '');
if ($consumer_email === '') {
  abort_page('Sessione utente non valida (email mancante).', 500, 'Pagamento');
}

$external_tx_id = "biglietto-$id_biglietto-" . time();
$return_url  = rtrim(PUBLIC_BASE_URL,   '/') . '/public/payment_return.php';
$webhook_url = rtrim(INTERNAL_BASE_URL, '/') . '/public/payment_webhook.php';

$payload = [
  'return_url'   => $return_url,
  'webhook_url'  => $webhook_url,
  'merchant_email' => $merchant_email,
  'consumer_email' => $consumer_email,
  'external_tx_id' => $external_tx_id,
  'descrizione'  => "Pagamento biglietto #$id_biglietto",
  'importo'      => $importo
];

$endpoint = rtrim(PAYSTEAM_BASE_URL, '/') . '/public/api/create.php';
$r = http_post_json($endpoint, $payload, ['Authorization: Bearer ' . PAYSTEAM_API_TOKEN]);
if (!$r['ok']) {
  $details = $r['body'] ?: ($r['error'] ?: 'nessun dettaglio');
  abort_page(
    'Errore dal provider pagamento (HTTP ' . (int)$r['code'] . '): ' . htmlspecialchars($details),
    502,
    'Pagamento'
  );
}
$js = $r['json'];
if (!$js || empty($js['auth_url'])) {
  abort_page('Risposta provider non valida.', 502, 'Pagamento');
}
header('Location: ' . $js['auth_url']);
exit;
