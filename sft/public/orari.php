<?php
$PAGE_CLASS = 'page-orari';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
redirect_front_to_backoffice_if_needed();
if (empty($_SESSION['user']) || $_SESSION['user']['ruolo'] !== RUOLO_UTENTE) {
  abort_page('Accesso riservato agli utenti registrati.', 403, 'Accesso negato');
}

require_once __DIR__ . '/../includes/header.php';
$res = q("SELECT c.id_corsa, c.data, c.ora_partenza, c.ora_arrivo,
                 t.codice AS treno, t.posti_totali,
                 sp.nome AS partenza, sa.nome AS arrivo, tr.distanza_km
          FROM p1_corse c
          JOIN p1_treni t   ON t.id_treno=c.id_treno
          JOIN p1_tratte tr ON tr.id_tratta=c.id_tratta
          JOIN p1_stazioni sp ON sp.id_stazione=tr.id_stazione_partenza
          JOIN p1_stazioni sa ON sa.id_stazione=tr.id_stazione_arrivo
          WHERE c.cancellata = 0
            AND c.data >= CURDATE()
          ORDER BY c.data, c.ora_partenza")->get_result();
$corse = $res->fetch_all(MYSQLI_ASSOC);
?>

<h2>Orari corse</h2>
<div class="table cols-7">
  <div class="row head">
    <div>Data</div>
    <div>Partenza</div>
    <div>Arrivo</div>
    <div>Treno</div>
    <div>Posti</div>
    <div>Prezzo</div>
    <div></div>
  </div>

  <?php foreach ($corse as $c):
    list($tot, $pren) = corsa_disponibilita($c['id_corsa']);
    $disp = max(0, $tot - $pren);
    $prezzo = number_format($c['distanza_km'] * TARIFFA_KM, 2, ',', '.');
  ?>
    <div class="row">
      <div><?= htmlspecialchars($c['data']) ?> <?= htmlspecialchars(substr($c['ora_partenza'], 0, 5)) ?></div>
      <div><?= htmlspecialchars($c['partenza']) ?></div>
      <div><?= htmlspecialchars($c['arrivo']) ?> (<?= htmlspecialchars(substr($c['ora_arrivo'], 0, 5)) ?>)</div>
      <div><?= htmlspecialchars($c['treno']) ?></div>
      <div><?= $disp ?> / <?= $c['posti_totali'] ?></div>
      <div>€ <?= $prezzo ?></div>
      <div><?php if ($disp > 0): ?><a class="btn" href="acquisto.php?id_corsa=<?= $c['id_corsa'] ?>">Acquista</a><?php else: ?>Esaurito<?php endif; ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>