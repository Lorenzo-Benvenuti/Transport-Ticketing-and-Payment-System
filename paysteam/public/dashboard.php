<?php
require_once __DIR__ . '/../includes/functions.php';

require_login();
$u = current_user();

// Aggiunge, cancella e seleziona le carte di credito
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  if (isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
      $nome = trim($_POST['nome'] ?? '');
      $saldo = floatval($_POST['saldo'] ?? 0);
      if ($nome !== '') {
        q("INSERT INTO p2_carte (id_utente, nome, saldo) VALUES (?, ?, ?)", [(int)$u['id'], $nome, $saldo], 'isd');
        redirect('dashboard.php');
      }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id_carta'])) {
      $idc = (int)$_POST['id_carta'];
      q("DELETE FROM p2_carte WHERE id_carta=? AND id_utente=?", [$idc, (int)$u['id']], 'ii');
      redirect('dashboard.php');
    } elseif ($_POST['action'] === 'select' && isset($_POST['id_carta'])) {
      $idc = (int)$_POST['id_carta'];
      $row = q("SELECT saldo, nome FROM p2_carte WHERE id_carta=? AND id_utente=?", [$idc, (int)$u['id']], 'ii')->get_result()->fetch_assoc();
      if ($row) {
        // Imposta il saldo dell'account come quello della carta
        q("UPDATE p2_conti SET saldo=? WHERE id_utente=?", [$row['saldo'], (int)$u['id']], 'di');
        // Registra nei movimenti la selezione della carta
        q(
          "INSERT INTO p2_movimenti (id_utente, data_mov, descrizione, importo, verso) VALUES (?, NOW(), ?, ?, 'ENTRATA')",
          [(int)$u['id'], "Selezionata carta: " . $row['nome'], $row['saldo']],
          'isd'
        );
        redirect('dashboard.php');
      }
    }
  }
}

// Fetch info account e movimenti
$cont = q("SELECT saldo FROM p2_conti WHERE id_utente=?", [(int)$u['id']], 'i')->get_result()->fetch_assoc();
$movs = q("SELECT data_mov, descrizione, importo, verso FROM p2_movimenti WHERE id_utente=? ORDER BY data_mov DESC LIMIT 20", [(int)$u['id']], 'i')->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch info carte
$cards = q("SELECT id_carta, nome, saldo FROM p2_carte WHERE id_utente=? ORDER BY id_carta", [(int)$u['id']], 'i')->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<h2>Dashboard</h2>

<div class="summary">
  <div class="pill">Saldo: € <?= money_fmt($cont['saldo'] ?? 0) ?></div>
  <div class="pill">Ruolo: <?= htmlspecialchars($u['ruolo']) ?></div>
</div>


<div class="card">
  <h3>Carte di credito</h3>
  <p>Aggiungi, elimina o seleziona una carta:</p>

  <div class="grid2">
    <div>
      <h4>Aggiungi carta</h4>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input type="hidden" name="action" value="add" />
        <label>Nome
          <input name="nome" required>
        </label>
        <label>Saldo
          <input name="saldo" type="number" step="0.01" min="0" required>
        </label>
        <div><button class="btn primary" type="submit">Aggiungi carta</button></div>
      </form>
    </div>

    <div>
      <h4>Carte disponibili</h4>
      <?php if (count($cards) === 0): ?>
        <p class="muted">Nessuna carta disponibile.</p>
      <?php else: ?>
        <table class="table">
          <tr>
            <th>Nome</th>
            <th>Saldo</th>
            <th>Azioni</th>
          </tr>
          <?php foreach ($cards as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['nome']) ?></td>
              <td>€ <?= money_fmt($c['saldo']) ?></td>
              <td>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="action" value="select" />
                  <input type="hidden" name="id_carta" value="<?= (int)$c['id_carta'] ?>" />
                  <button class="btn primary" type="submit">Seleziona</button>
                </form>
                <form method="post" style="display:inline;margin-left:6px">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id_carta" value="<?= (int)$c['id_carta'] ?>" />
                  <button class="btn" type="submit" onclick="return confirm('Confermi eliminazione?')">Elimina</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>