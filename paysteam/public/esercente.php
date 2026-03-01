<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';

// Merchant: se non autenticato, vai al login staff
require_login('login.php?area=1');

$u = current_user();
require_role(RUOLO_ESERCENTE);

$u = current_user();
$cont = q("SELECT saldo FROM p2_conti WHERE id_utente=?", [(int)$u['id']], 'i')->get_result()->fetch_assoc();
$entrate = q("SELECT SUM(importo) as tot FROM p2_movimenti WHERE id_utente=? AND verso='ENTRATA'", [(int)$u['id']], 'i')->get_result()->fetch_assoc()['tot'] ?? 0;
?>

<h2>Area Esercente</h2>
<div class="summary">
  <div class="pill">Saldo esercente: € <?= money_fmt($cont['saldo'] ?? 0) ?></div>
  <div class="pill">Entrate totali: € <?= money_fmt($entrate) ?></div>
</div>

<?php $movs = q("SELECT data_mov, descrizione, importo, verso FROM p2_movimenti WHERE id_utente=? ORDER BY data_mov DESC", [(int)$u['id']], 'i')->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<h3>Tutti i movimenti</h3>
<table class="table">
  <tr>
    <th>Data</th>
    <th>Descrizione</th>
    <th>Importo</th>
    <th>Verso</th>
  </tr>
  <?php foreach ($movs as $m): ?>
    <tr>
      <td><?= htmlspecialchars($m['data_mov']) ?></td>
      <td><?= htmlspecialchars($m['descrizione']) ?></td>
      <td>€ <?= money_fmt($m['importo']) ?></td>
      <td><?= htmlspecialchars($m['verso']) ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>