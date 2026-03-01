<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

login_required();

$uid = (int)$_SESSION['user']['id'];
$err = "";
$msg = "";

$id_biglietto = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id_biglietto']) ? (int)$_POST['id_biglietto'] : 0);

// Carica biglietto utente
$st = q(
  "SELECT b.*, c.id_tratta, c.id_treno, c.data, c.ora_partenza, tr.distanza_km, t.posti_totali,
                sp.nome AS partenza, sa.nome AS arrivo, t.codice AS codice_treno
         FROM p1_biglietti b
         JOIN p1_corse c   ON c.id_corsa=b.id_corsa
         JOIN p1_tratte tr ON tr.id_tratta=c.id_tratta
         JOIN p1_treni  t  ON t.id_treno=c.id_treno
         JOIN p1_stazioni sp ON sp.id_stazione=tr.id_stazione_partenza
         JOIN p1_stazioni sa ON sa.id_stazione=tr.id_stazione_arrivo
         WHERE b.id_biglietto=? AND b.id_utente=?",
  [$id_biglietto, $uid],
  'ii'
);

$ticket_rs = $st ? $st->get_result() : null;
$ticket = $ticket_rs ? $ticket_rs->fetch_assoc() : null;

if (!$ticket) {
  require_once __DIR__ . '/../includes/header.php';
  echo "<div class='container'><p class='alert error'>Biglietto non trovato.</p></div>";
  require_once __DIR__ . '/../includes/footer.php';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione']) && $_POST['azione'] === 'salva') {
  if (ENABLE_CSRF) { csrf_check(); }

  $id_corsa_new = isset($_POST['id_corsa']) ? (int)$_POST['id_corsa'] : 0;
  $posto_new    = isset($_POST['posto']) ? (int)$_POST['posto'] : 0;

  if ($id_corsa_new <= 0) {
    $err = "Seleziona una corsa valida.";
  } else {
    $stCheck = q(
      "SELECT c.id_corsa, t.posti_totali,
                             (SELECT COUNT(*) FROM p1_biglietti b WHERE b.id_corsa=c.id_corsa AND b.id_biglietto<>?) AS venduti
                      FROM p1_corse c
                      JOIN p1_treni t ON t.id_treno=c.id_treno
                      WHERE c.id_corsa=? AND c.cancellata=0 AND c.data>=CURDATE()",
      [$id_biglietto, $id_corsa_new],
      'ii'
    );
    $ok = false;
    if ($stCheck && ($rsC = $stCheck->get_result())) {
      if ($rowC = $rsC->fetch_assoc()) {
        $liberi = (int)$rowC['posti_totali'] - (int)$rowC['venduti'];
        $ok = ($liberi > 0);
      }
    }
    if (!$ok) {
      $err = "La corsa selezionata non ha posti disponibili o non è valida.";
    } else {
      if ($posto_new <= 0) {
        $err = "Seleziona un posto valido.";
      } else {
        $stBusy = q(
          "SELECT 1 FROM p1_biglietti WHERE id_corsa=? AND posto=? AND id_biglietto<>?",
          [$id_corsa_new, $posto_new, $id_biglietto],
          'iii'
        );
        $busy = false;
        if ($stBusy) {
          $rB = $stBusy->get_result();
          $busy = ($rB && $rB->num_rows > 0);
        }
        if ($busy) {
          $err = "Il posto selezionato non è più disponibile.";
        } else {
          $stUp = q(
            "UPDATE p1_biglietti SET id_corsa=?, posto=? WHERE id_biglietto=? AND id_utente=?",
            [$id_corsa_new, $posto_new, $id_biglietto, $uid],
            'iiii'
          );
          if ($stUp) {
            header("Location: modifica_biglietto.php?id=" . $id_biglietto . "&ok=1");
            exit;
          } else {
            $err = "Errore durante l'aggiornamento.";
          }
        }
      }
    }
  }
}

$st2 = q("SELECT c.id_corsa, c.data, c.ora_partenza,
                 t.codice AS treno, t.posti_totali,
                 sp.nome AS partenza, sa.nome AS arrivo,
                 (SELECT COUNT(*) FROM p1_biglietti b WHERE b.id_corsa=c.id_corsa) AS venduti
          FROM p1_corse c
          JOIN p1_treni t ON t.id_treno=c.id_treno
          JOIN p1_tratte tr ON tr.id_tratta=c.id_tratta
          JOIN p1_stazioni sp ON sp.id_stazione=tr.id_stazione_partenza
          JOIN p1_stazioni sa ON sa.id_stazione=tr.id_stazione_arrivo
          WHERE c.cancellata=0 AND c.data >= CURDATE()
            AND (SELECT COUNT(*) FROM p1_biglietti b WHERE b.id_corsa=c.id_corsa) < t.posti_totali
          ORDER BY c.data, c.ora_partenza");
$corse = $st2 ? $st2->get_result()->fetch_all(MYSQLI_ASSOC) : [];

$selected_corsa = isset($_POST['id_corsa']) ? (int)$_POST['id_corsa'] : (int)$ticket['id_corsa'];

$posti_totali_sel = 0;
$occ = [];

$stPT = q(
  "SELECT t.posti_totali FROM p1_corse c JOIN p1_treni t ON t.id_treno=c.id_treno WHERE c.id_corsa=?",
  [$selected_corsa],
  'i'
);
if ($stPT && ($rPT = $stPT->get_result())) {
  if ($rowPT = $rPT->fetch_assoc()) {
    $posti_totali_sel = (int)$rowPT['posti_totali'];
  }
}

$stSeats = q(
  "SELECT posto FROM p1_biglietti WHERE id_corsa=? AND id_biglietto<>?",
  [$selected_corsa, $id_biglietto],
  'ii'
);
if ($stSeats && ($rsS = $stSeats->get_result())) {
  $occ = array_column($rsS->fetch_all(MYSQLI_ASSOC), 'posto');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
  <?php if ($err): ?><p class="alert error"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <?php if ($msg || isset($_GET['ok'])): ?><p class="alert success">Biglietto aggiornato.</p><?php endif; ?>

  <h1>Modifica biglietto</h1>

  <div class="card">
    <p><strong>Tratta attuale:</strong> <?= htmlspecialchars($ticket['partenza'] . " → " . $ticket['arrivo']) ?></p>
    <p><strong>Corsa attuale:</strong> <?= htmlspecialchars($ticket['data'] . " " . $ticket['ora_partenza'] . " — Treno " . $ticket['codice_treno']) ?></p>
    <p><strong>Posto attuale:</strong> <?= (int)$ticket['posto'] ?></p>
  </div>

  <form method="post" class="form">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

    <input type="hidden" name="id_biglietto" value="<?= $id_biglietto ?>">

    <label>Nuova corsa:
      <select name="id_corsa" onchange="this.form.submit()">
        <?php if (!$corse): ?>
          <option value="">Nessuna corsa disponibile</option>
        <?php else: foreach ($corse as $c):
          $sel = ((int)$c['id_corsa'] === $selected_corsa) ? 'selected' : '';
          $liberi = (int)$c['posti_totali'] - (int)$c['venduti'];
          $lbl = sprintf('%s %s — %s→%s — Treno %s — %d posti liberi', $c['data'], $c['ora_partenza'], $c['partenza'], $c['arrivo'], $c['treno'], $liberi);
        ?>
          <option value="<?= $c['id_corsa'] ?>" <?= $sel ?>><?= htmlspecialchars($lbl) ?></option>
        <?php endforeach; endif; ?>
      </select>
    </label>

    <label>Nuovo posto disponibile:
      <select name="posto">
        <?php
        for ($i = 1; $i <= max(1, $posti_totali_sel); $i++) {
          if (!in_array($i, $occ)) {
            $sel = ((int)$ticket['posto'] === $i && $selected_corsa == (int)$ticket['id_corsa']) ? 'selected' : '';
            echo '<option value="' . $i . '" ' . $sel . '>' . $i . '</option>';
          }
        }
        ?>
      </select>
    </label>

    <button type="submit" class="btn" name="azione" value="salva">Conferma modifica</button>
    <a class="btn" href="acquisto.php">Annulla</a>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>