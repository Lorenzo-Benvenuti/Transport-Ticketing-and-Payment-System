<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
redirect_front_to_backoffice_if_needed();
login_required();
require_once __DIR__ . '/../includes/header.php';

$err = "";
$msg = "";

// Determina la corsa selezionata da POST o GET
$selected_id_corsa = 0;
if (isset($_POST['id_corsa'])) {
  $selected_id_corsa = (int)$_POST['id_corsa'];
} elseif (isset($_GET['id_corsa'])) {
  $selected_id_corsa = (int)$_GET['id_corsa'];
}

// Gestione acquisto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (ENABLE_CSRF) { csrf_check(); }


  $posto = isset($_POST['posto']) ? (int)$_POST['posto'] : 0;

  if ($selected_id_corsa <= 0 || $posto <= 0) {
    $err = "Dati mancanti";
  } else {
    // Carica info corsa (capienza e distanza per prezzo)
    $stmt = q("SELECT c.id_corsa, c.id_treno, t.codice AS treno, t.posti_totali,
                      tr.distanza_km, sp.nome AS partenza, sa.nome AS arrivo, c.data, c.ora_partenza
               FROM p1_corse c
               JOIN p1_treni t ON t.id_treno=c.id_treno
               JOIN p1_tratte tr ON tr.id_tratta=c.id_tratta
               JOIN p1_stazioni sp ON sp.id_stazione=tr.id_stazione_partenza
               JOIN p1_stazioni sa ON sa.id_stazione=tr.id_stazione_arrivo
               WHERE c.id_corsa=?", [$selected_id_corsa], 'i');
    $res = $stmt->get_result();
    $info = $res->fetch_assoc();

    if (!$info) {
      $err = "Corsa non trovata";
    } else {
      // Valida range posto
      if ($posto < 1 || $posto > (int)$info['posti_totali']) {
        $err = "Numero posto non valido (1.." . (int)$info['posti_totali'] . ")";
      } else {
        // Controlla occupazione posti
        $st2 = q("SELECT COUNT(*) AS cnt FROM p1_biglietti WHERE id_corsa=? AND posto=?", [$selected_id_corsa, $posto], 'ii');
        $cnt = $st2->get_result()->fetch_assoc();
        if ($cnt && (int)$cnt['cnt'] > 0) {
          $err = "Posto già occupato. Scegline un altro.";
        } else {
          // Calcola prezzo
          $prezzo = round(((float)$info['distanza_km']) * (float)TARIFFA_KM, 2);
          // Inserisce biglietto prenotato
          $uid = (int)$_SESSION['user']['id'];
          $ins = q(
            "INSERT INTO p1_biglietti (id_utente, id_corsa, posto, prezzo) VALUES (?,?,?,?)",
            [$uid, $selected_id_corsa, $posto, $prezzo],
            "iiid"
          );

          if ($ins->errno === 1062) {
            $err = "Posto già occupato. Scegline un altro.";
          } elseif ($ins->errno) {
            $err = "Errore durante la prenotazione. Riprova.";
          } else {
            $msg = 'Biglietto prenotato (da pagare). Scorri in basso a "Biglietti prenotati" per completare il pagamento su PaySteam.';
          }
      }
    }
  }
}
}


// Lista corse future
$stmtList = q("SELECT c.id_corsa, c.data, c.ora_partenza, t.codice AS treno, t.posti_totali,
                      sp.nome AS partenza, sa.nome AS arrivo, tr.distanza_km,
                      (t.posti_totali - COUNT(b.id_biglietto)) AS posti_liberi
               FROM p1_corse c
               JOIN p1_treni t ON t.id_treno=c.id_treno
               JOIN p1_tratte tr ON tr.id_tratta=c.id_tratta
               JOIN p1_stazioni sp ON sp.id_stazione=tr.id_stazione_partenza
               JOIN p1_stazioni sa ON sa.id_stazione=tr.id_stazione_arrivo
               LEFT JOIN p1_biglietti b ON b.id_corsa=c.id_corsa
               WHERE c.cancellata=0
               GROUP BY c.id_corsa, c.data, c.ora_partenza, t.codice, t.posti_totali, sp.nome, sa.nome, tr.distanza_km
               HAVING posti_liberi > 0
               ORDER BY c.data ASC, c.ora_partenza ASC");
$corse = $stmtList->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch info corsa selezionata
$info = null;
if ($selected_id_corsa > 0) {
  foreach ($corse as $r) {
    if ((int)$r['id_corsa'] === $selected_id_corsa) {
      $info = $r;
      break;
    }
  }
  if (!$info) {
    // Fallback fetch se la corsa non fosse presente nella lista di quelle future
    $stx = q("SELECT c.id_corsa, c.data, c.ora_partenza, t.codice AS treno, t.posti_totali,
                     sp.nome AS partenza, sa.nome AS arrivo, tr.distanza_km
              FROM p1_corse c
              JOIN p1_treni t ON t.id_treno=c.id_treno
              JOIN p1_tratte tr ON tr.id_tratta=c.id_tratta
              JOIN p1_stazioni sp ON sp.id_stazione=tr.id_stazione_partenza
              JOIN p1_stazioni sa ON sa.id_stazione=tr.id_stazione_arrivo
              WHERE c.id_corsa=?", [$selected_id_corsa], 'i');
    $info = $stx->get_result()->fetch_assoc();
  }
}
?>
<h2>Prenotazione Biglietto</h2>

<?php if ($msg): ?><div class="alert success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert error"><?= $err ?></div><?php endif; ?>

<form method="post" class="form">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

  <label>Seleziona corsa:
    <select name="id_corsa" required>
      <option value="">— scegli —</option>
      <?php foreach ($corse as $c):
        $optLbl = sprintf(
          "%s %s %s→%s — Treno %s — %.0f posti",
          htmlspecialchars($c['data']),
          substr($c['ora_partenza'], 0, 5),
          htmlspecialchars($c['partenza']),
          htmlspecialchars($c['arrivo']),
          htmlspecialchars($c['treno']),
          (float)$c['posti_totali']
        );
        $sel = ($selected_id_corsa === (int)$c['id_corsa']) ? 'selected' : '';
      ?>
        <option value="<?= $c['id_corsa'] ?>" <?= $sel ?>><?= $optLbl ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Numero posto (1, .., capienza):
    <input type="number" name="posto" min="1" required value="<?= isset($_POST['posto']) ? (int)$_POST['posto'] : '' ?>">
  </label>

  <button type="submit" class="btn">Conferma prenotazione</button>
</form>

<?php if ($info):
  $prezzoPrev = round(((float)$info['distanza_km']) * (float)TARIFFA_KM, 2);
?>
  <div class="card" style="margin-top:1rem">
    <p><strong>Corsa:</strong> Treno <?= htmlspecialchars($info['treno']) ?> — <?= htmlspecialchars($info['partenza']) ?> → <?= htmlspecialchars($info['arrivo']) ?> — <?= htmlspecialchars($info['data']) ?> (<?= substr($info['ora_partenza'], 0, 5) ?>)</p>
    <p><strong>Prezzo stimato:</strong> € <?= number_format($prezzoPrev, 2, ',', '.') ?> — <strong>Tariffa</strong> <?= TARIFFA_KM ?> €/km</p>
  </div>
<?php endif; ?>

<hr style="margin:2rem 0">

<h2>Biglietti acquistati</h2>
<?php
$uid = (int)$_SESSION['user']['id'];
$stmtB = q("SELECT b.id_biglietto, b.posto, b.prezzo, b.id_corsa,
                       c.data, c.ora_partenza, c.cancellata,
                       t.codice AS treno, t.posti_totali,
                       sp.nome AS partenza, sa.nome AS arrivo , b.pagato FROM p1_biglietti b
                JOIN p1_corse c ON c.id_corsa=b.id_corsa
                JOIN p1_treni t ON t.id_treno=c.id_treno
                JOIN p1_tratte tr ON tr.id_tratta=c.id_tratta
                JOIN p1_stazioni sp ON sp.id_stazione=tr.id_stazione_partenza
                JOIN p1_stazioni sa ON sa.id_stazione=tr.id_stazione_arrivo
                WHERE b.id_utente=?
                ORDER BY c.data DESC, c.ora_partenza DESC", [$uid], 'i');
$myTickets = $stmtB->get_result()->fetch_all(MYSQLI_ASSOC); ?>

<?php
$paidTickets = array_values(array_filter($myTickets, function ($r) {
  return !empty($r['pagato']) && (int)$r['pagato'] === 1;
}));
?>

<?php if (empty($paidTickets)): ?>
  <p class="muted">Non hai ancora biglietti acquistati.</p>
<?php else: ?>
  <div class="table cols-4">
    <div class="row head">
      <div>Corsa</div>
      <div>Data</div>
      <div>Posto</div>
      <div>Status</div>
    </div>
    <?php foreach ($paidTickets as $t):
      $quando = htmlspecialchars($t['data']) . ' ' . htmlspecialchars(substr($t['ora_partenza'], 0, 5));
      $corsa  = 'Treno ' . htmlspecialchars($t['treno']) . ' — ' . htmlspecialchars($t['partenza']) . ' → ' . htmlspecialchars($t['arrivo']);
      $posto = htmlspecialchars($t['posto'] ?? '-');
      $status = $t['cancellata'] ? 'Cancellata' : 'Pagato';
    ?>
      <div class="row">
        <div><?= $corsa ?></div>
        <div><?= $quando ?></div>
        <div><?= $posto ?></div>
        <div><?= $status ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>


<hr style="margin:2rem 0">
<h2>Biglietti prenotati</h2>

<?php
// Filtra i biglietti ancora da pagare (pagato != 1)
$toPay = array_values(array_filter($myTickets, function ($r) {
  $isPaid = isset($r['pagato']) && (int)$r['pagato'] === 1;
  $isCancelled = isset($r['cancellata']) && (int)$r['cancellata'] === 1;
  return !$isPaid && !$isCancelled; // Le corse annullate non compaiono nel menù PaySteam
}));
?>

<?php if (empty($toPay)): ?>
  <p>Non hai ancora prenotato alcun biglietto.</p>
<?php else: ?>
  <?php foreach ($toPay as $r): ?>
    <?php $isPaid = isset($r['pagato']) ? (int)$r['pagato'] === 1 : 0; ?>
    <div class="card">
      <p><strong>Corsa:</strong> Treno <?= htmlspecialchars($r['treno']) ?> — <?= htmlspecialchars($r['partenza']) ?> → <?= htmlspecialchars($r['arrivo']) ?></p>
      <p><strong>Data:</strong> <?= htmlspecialchars($r['data']) ?> alle <?= substr($r['ora_partenza'], 0, 5) ?></p>
      <p><strong>Posto:</strong> <?= htmlspecialchars($r['posto'] ?? '-') ?></p>
      <p><strong>Status:</strong> <?php echo $isPaid ? '<span class="badge">Pagato</span>' : '<span class="badge">Da pagare</span>'; ?></p>
      <?php if (!$r['cancellata']): ?>
        <a class="btn" href="modifica_biglietto.php?id=<?= $r['id_biglietto'] ?>">Modifica</a>
      <?php else: ?>
        <span class="badge">Corsa cancellata</span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<hr style="margin:2rem 0">
<h3>Paga tramite PaySteam</h3>
<div class="card">
  <p>Seleziona uno dei tuoi biglietti prenotati e procedi al pagamento tramite provider esterno:</p>
  <?php if (!empty($toPay)): ?>
    <form method="get" action="payment_start.php" class="paysteam-form">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

      <label style="display:block;">Biglietto
        <select name="id_biglietto" required>
          <option value="" disabled selected>Seleziona biglietto…</option>
          <?php foreach ($toPay as $r):
            $isPaid = isset($r['pagato']) ? (int)$r['pagato'] === 1 : 0;
            $label = sprintf(
              '#%s — %s — %s %s — %s → %s (posto %s) — € %s',
              (string)(int)$r['id_biglietto'],
              htmlspecialchars($r['treno']),
              htmlspecialchars($r['data']),
              htmlspecialchars(substr($r['ora_partenza'], 0, 5)),
              htmlspecialchars($r['partenza']),
              htmlspecialchars($r['arrivo']),
              htmlspecialchars((string)$r['posto']),
              number_format((float)$r['prezzo'], 2, ',', '.')
            );
          ?>
            <option value="<?= $r['id_biglietto'] ?>"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div style="margin-top:10px;"><button class="btn primary">Paga con PaySteam</button></div>
    </form>
</div>
<?php else: ?>
  <p class="muted">Non hai biglietti da pagare.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>