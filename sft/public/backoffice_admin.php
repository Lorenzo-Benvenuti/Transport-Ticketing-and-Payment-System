<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

role_required(RUOLO_ADMIN);

if (isset($_POST["azione"]) && $_POST["azione"] === "cancella" && !empty($_POST["id_corsa"])) {
  if (ENABLE_CSRF) { csrf_check(); }
  q("UPDATE p1_corse SET cancellata=1 WHERE id_corsa=?", [(int)$_POST["id_corsa"]], "i");
}


$corse = q("SELECT c.id_corsa, c.data, c.ora_partenza, c.cancellata,
                   sp.nome AS partenza, sa.nome AS arrivo,
                   COALESCE(SUM(b.prezzo),0) AS incasso, COUNT(b.id_biglietto) AS venduti
            FROM p1_corse c
            JOIN p1_tratte tr ON tr.id_tratta=c.id_tratta
            JOIN p1_stazioni sp ON sp.id_stazione=tr.id_stazione_partenza
            JOIN p1_stazioni sa ON sa.id_stazione=tr.id_stazione_arrivo
            LEFT JOIN p1_biglietti b ON b.id_corsa=c.id_corsa
            GROUP BY c.id_corsa
            ORDER BY c.data DESC, c.ora_partenza DESC")->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<h2>Backoffice amministrativo</h2>
<form method="post" class="inline">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

  <div class="toolbar">
    <a class="btn" href="richiesta_treno.php">Richiedi treno straordinario</a>
  </div>
</form>
<div class="table cols-6">
  <div class="row head">
    <div>Corsa</div>
    <div>Tratta</div>
    <div>Incasso</div>
    <div>Venduti</div>
    <div>Stato</div>
    <div>Azioni</div>
  </div>
  <?php foreach ($corse as $c): ?>
    <div class="row">
      <div>#<?= $c['id_corsa'] ?> (<?= $c['data'] ?> <?= $c['ora_partenza'] ?>)</div>
      <div><?= $c['partenza'] ?> → <?= $c['arrivo'] ?></div>
      <div>€ <?= number_format($c['incasso'], 2, ',', '.') ?></div>
      <div><?= $c['venduti'] ?></div>
      <div><?= $c['cancellata'] ? 'Cancellata' : 'Attiva' ?></div>
      <div>
        <?php if (!$c['cancellata']): ?>
          <form method="post" class="inline">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

            <input type="hidden" name="id_corsa" value="<?= $c['id_corsa'] ?>">
            <button class="btn danger" name="azione" value="cancella">Cancella</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<div class="table">
  <div class="row head">Stato Richieste</div>
  <div class="request-list">
    <?php
    $res = q(
      "SELECT * FROM p1_richieste_treni WHERE id_admin=? ORDER BY creata_il DESC",
      [$_SESSION['user']['id']],
      'i'
    );
    $reqs = $res->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($reqs as $r): ?>
      <div class="card">
        <p><strong>Messaggio:</strong> <?= htmlspecialchars($r['messaggio']) ?></p>
        <p><strong>Risposta:</strong> <?= htmlspecialchars((string)($r['risposta'] ?? '')) ?></p>
        <p><strong>Stato:</strong> <?= htmlspecialchars($r['stato']) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>