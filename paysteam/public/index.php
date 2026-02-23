<?php
require_once __DIR__ . '/../includes/functions.php';

// Landing pubblica: se l'utente è già autenticato, reindirizza alla sua area.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!empty($_SESSION['user'])) {
  $u = $_SESSION['user'];
  if (!empty($u['ruolo']) && $u['ruolo'] === RUOLO_ESERCENTE) {
    redirect('esercente.php');
  }
  redirect('dashboard.php');
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="hero">
  <h1>Benvenuto su <?= htmlspecialchars(APP_NAME) ?> </h1>
  <p>Piattaforma per il pagamento: effettua il login per gestire il tuo conto, visualizzare i movimenti ed approvare i pagamenti.</p>
</section>
<section class="cards">
  <div class="card">
    <h3>Per i consumatori</h3>
    <p>Controllo saldo e movimenti, gestione carte e conferma transazioni.</p>
  </div>
  <div class="card">
    <h3>Per gli esercenti</h3>
    <p>Ricezione pagamenti e monitoraggio incassi.</p>
  </div>
  <div class="card">
    <h3>API M2M</h3>
    <p>Endpoint sicuro con Bearer token e callback firmata HMAC.</p>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>