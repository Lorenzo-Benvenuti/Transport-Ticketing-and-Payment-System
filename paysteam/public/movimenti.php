<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_once __DIR__ . '/../includes/header.php';

$u = current_user();
$movs = q("SELECT data_mov, descrizione, importo, verso FROM p2_movimenti WHERE id_utente=? ORDER BY data_mov DESC", [(int)$u['id']], 'i')->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<h2>Tutti i movimenti</h2>
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