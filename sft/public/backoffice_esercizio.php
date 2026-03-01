<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
role_required(RUOLO_ESERCIZIO);

// Regole operative corse
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $azione = isset($_POST['azione']) ? $_POST['azione'] : (isset($azione) ? $azione : '');
  if (isset($_POST['azione']) && $_POST['azione'] === 'add_corsa') {
    $id_treno     = (int)$_POST['id_treno'];
    $id_tratta    = (int)$_POST['id_tratta'];
    $data         = $_POST['data'];
    $ora_partenza = $_POST['ora_partenza'];

    // Vieta nuove corse su treni disattivati/eliminati
    $treno_ok = q(
      "SELECT 1 FROM p1_treni WHERE id_treno = ? AND disattivo = 0",
      [$id_treno],
      'i'
    )->get_result()->fetch_row();

    if (!$treno_ok) {
      $_SESSION['flash_error'][] = "Treno non disponibile per creare nuove corse (treno disattivato).";
      header('Location: backoffice_esercizio.php');
      exit;
    }

    // 1) Calcolo orari effettivi in base a traffico/incroci sulla stessa tratta fisica
    list($ora_partenza_eff, $ora_arrivo_calcolata, $attesa_min) =
      calcola_orari_corsa($id_treno, $id_tratta, $data, $ora_partenza);

    // 2) Controllo conflitti usando gli orari effettivi
    if (corsa_conflitto_treno($id_treno, $data, $ora_partenza_eff, $ora_arrivo_calcolata)) {
      $_SESSION['flash_error'][] = "Conflitto: il treno è già impegnato in una corsa sovrapposta.";
      header('Location: backoffice_esercizio.php');
      exit;
    }

    $err = valida_corsa_operativa($id_treno, $id_tratta, $data, $ora_partenza_eff, $ora_arrivo_calcolata);
    if ($err) {
      $_SESSION['flash_error'][] = $err;
      header('Location: backoffice_esercizio.php');
      exit;
    }

    // 3) Crea un nuova corsa con gli orari effettivi
    q(
      "INSERT INTO p1_corse (id_treno,id_tratta,data,ora_partenza,ora_arrivo,cancellata)
         VALUES (?,?,?,?,?,0)",
      [$id_treno, $id_tratta, $data, $ora_partenza_eff, $ora_arrivo_calcolata],
      'iisss'
    );

    // 4) Messaggio esito
    $_SESSION['flash_success'][] =
      $attesa_min > 0
      ? ("Corsa creata. Attesa applicata: " . $attesa_min . " min. Arrivo: " . $ora_arrivo_calcolata)
      : ("Corsa creata senza attese. Arrivo: " . $ora_arrivo_calcolata);

    header('Location: backoffice_esercizio.php');
    exit;
  }
}

// Moduli inserimento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $azione = (isset($_POST['azione']) ? $_POST['azione'] : '');
  // Inserisce nuovo materiale rotabile
  if ($azione === 'add_mezzo') {
    q(
      "INSERT INTO p1_materiale_rotabile (tipo,modello,posti) VALUES (?,?,?)",
      [$_POST['tipo'], $_POST['modello'], (int)$_POST['posti']],
      'ssi'
    );
    $_SESSION['flash_success'][] = "Materiale rotabile creato con successo.";
    header('Location: backoffice_esercizio.php');
    exit;
  }
  // Rimuove materiale rotabile esistente
  if ($azione === 'del_mezzo') {
    $id = isset($_POST['id_mezzo']) ? (int)$_POST['id_mezzo'] : 0;
    if ($id > 0) {
      $exists = q("SELECT 1 FROM p1_materiale_rotabile WHERE id_mezzo=?", [$id], 'i')->get_result()->fetch_row();
      if ($exists) {
        // Non consente la rimozione se il mezzo è in uso in una composizione di treno
        $in_use_rs = q("SELECT tm.id_treno, t.codice FROM p1_treni_mezzi tm JOIN p1_treni t ON t.id_treno=tm.id_treno WHERE tm.id_mezzo=?", [$id], 'i')->get_result();
        if ($in_use_rs->num_rows > 0) {
          $list = [];
          while ($r = $in_use_rs->fetch_assoc()) {
            $list[] = $r['codice'];
          }
          $codes = htmlspecialchars(implode(', ', $list));
          $_SESSION['flash_error'][] = "Impossibile rimuovere: il mezzo è usato nella composizione dei seguenti treni: " . $codes . ".";
          header('Location: backoffice_esercizio.php');
          exit;
        } else {
          // Elimina eventuali legami in p1_treni_mezzi
          q("DELETE FROM p1_materiale_rotabile WHERE id_mezzo=?", [$id], 'i');
          $_SESSION['flash_success'][] = "Materiale rotabile rimosso.";
          header('Location: backoffice_esercizio.php');
          exit;
        }
      } else {
        $_SESSION['flash_error'][] = "Mezzo non trovato.";
        header('Location: backoffice_esercizio.php');
        exit;
      }
    } else {
      $_SESSION['flash_error'][] = "ID non valido.";
      header('Location: backoffice_esercizio.php');
      exit;
    }
  }
  // Inserisce nuovo treno
  if ($azione === 'add_treno') {
    q(
      "INSERT INTO p1_treni (codice,velocita_media,posti_totali) VALUES (?,?,0)",
      [(isset($_POST['codice']) ? $_POST['codice'] : ('T' . time())), (float)((isset($_POST['velocita_media']) ? $_POST['velocita_media'] : VEL_MEDIA_DEFAULT))],
      'sd'
    );
    $_SESSION['flash_success'][] = "Treno creato con successo.";
    header('Location: backoffice_esercizio.php');
    exit;
  }
  // Aggiunge componente rotabile al treno
  if ($azione === 'compose') {
    $id_treno = (int)$_POST['id_treno'];
    $id_mezzo = (int)$_POST['id_mezzo'];
    $qta = max(1, (int)$_POST['quantita']);
    // Blocco: mezzo già assegnato ad altro treno
    $gia_usato_altrove = q(
      "SELECT 1 FROM p1_treni_mezzi WHERE id_mezzo = ? AND id_treno <> ?",
      [$id_mezzo, $id_treno],
      'ii'
    )->get_result()->fetch_row();

    if ($gia_usato_altrove) {
      $_SESSION['flash_error'][] = "Il mezzo selezionato è già assegnato a un altro treno.";
      header('Location: backoffice_esercizio.php');
      exit;
    }
    $exist = q("SELECT 1 FROM p1_treni_mezzi WHERE id_treno=? AND id_mezzo=?", [$id_treno, $id_mezzo], 'ii')->get_result()->fetch_row();
    if ($exist) q("UPDATE p1_treni_mezzi SET quantita=quantita+? WHERE id_treno=? AND id_mezzo=?", [$qta, $id_treno, $id_mezzo], 'iii');
    else q("INSERT INTO p1_treni_mezzi (id_treno,id_mezzo,quantita) VALUES (?,?,?)", [$id_treno, $id_mezzo, $qta], 'iii');
    aggiorna_posti_totali_treno($id_treno);
    $_SESSION['flash_success'][] = "Componente aggiunta al treno.";
    header('Location: backoffice_esercizio.php');
    exit;
  }
  // Cancella un treno esistente
  if ($azione === 'delete_treno') {
    $id_treno = (int)$_POST['id_treno'];
    // Blocca se il treno è usato in corse attive o future (non cancellate)
    $in_uso = q("
  SELECT 1
  FROM p1_corse
  WHERE id_treno = ?
    AND cancellata = 0
    AND TIMESTAMP(data, ora_arrivo) >= NOW()  -- attive ora o future
  LIMIT 1
", [$id_treno], 'i')->get_result()->fetch_row();

    if ($in_uso) {
      $_SESSION['flash_error'][] = "Impossibile eliminare: il treno è usato in corse attive o programmate.";
      header('Location: backoffice_esercizio.php');
      exit;
    }

    // Soft-delete
    q("UPDATE p1_treni SET disattivo = 1 WHERE id_treno = ?", [$id_treno], 'i');
    $_SESSION['flash_success'][] = "Treno eliminato (disattivato).";
    header('Location: backoffice_esercizio.php');
    exit;
  }
  // Rimuove un materiale rotabile da un treno
  if ($azione === 'remove_mezzo_treno') {
    $id_treno = (int)$_POST['id_treno'];
    $id_mezzo = (int)$_POST['id_mezzo'];

    // Blocca se ci sono corse attive o future
    $in_uso = q("
  SELECT 1
  FROM p1_corse
  WHERE id_treno = ?
    AND cancellata = 0
    AND TIMESTAMP(data, ora_arrivo) >= NOW()
  LIMIT 1
", [$id_treno], 'i')->get_result()->fetch_row();

    if ($in_uso) {
      $_SESSION['flash_error'][] = "Impossibile modificare la composizione: treno usato in corse attive o programmate.";
      header('Location: backoffice_esercizio.php');
      exit;
    }

    q("DELETE FROM p1_treni_mezzi WHERE id_treno = ? AND id_mezzo = ?", [$id_treno, $id_mezzo], 'ii');

    // Aggiorna i posti totali del treno
    aggiorna_posti_totali_treno($id_treno);

    $_SESSION['flash_success'][] = "Mezzo rimosso dal treno.";
    header('Location: backoffice_esercizio.php');
    exit;
  }
  // Inserisce nuova tratta
  $azione = $_POST['azione'] ?? '';
  if ($azione === 'add_subtratta') {
    $sp = (int)(isset($_POST['id_stazione_partenza']) ? $_POST['id_stazione_partenza'] : 0);
    $sa = (int)(isset($_POST['id_stazione_arrivo']) ? $_POST['id_stazione_arrivo'] : 0);

    $rowP = q("SELECT km_progressivo AS p FROM p1_stazioni WHERE id_stazione=?", [$sp], 'i')->get_result()->fetch_assoc();
    $rowA = q("SELECT km_progressivo AS p FROM p1_stazioni WHERE id_stazione=?", [$sa], 'i')->get_result()->fetch_assoc();

    if (!$sp || !$sa) {
      $_SESSION['flash_error'][] = "Seleziona entrambe le stazioni.";
      header('Location: backoffice_esercizio.php');
      exit;
    } elseif ($sp === $sa) {
      $_SESSION['flash_error'][] = "La stazione di partenza e quella di arrivo devono essere diverse.";
      header('Location: backoffice_esercizio.php');
      exit;
    } elseif (!$rowP || !$rowA) {
      $_SESSION['flash_error'][] = "Mancano i km per una o entrambe le stazioni.";
      header('Location: backoffice_esercizio.php');
      exit;
    } else {
      $p1 = (float)$rowP['p'];
      $p2 = (float)$rowA['p'];
      $dkm = abs($p2 - $p1); // Calcolo server obbligatorio

      $dup = q("SELECT 1 FROM p1_tratte WHERE id_stazione_partenza=? AND id_stazione_arrivo=?", [$sp, $sa], 'ii')->get_result()->fetch_row();
      if ($dup) {
        $_SESSION['flash_error'][] = "Esiste già una tratta con queste stazioni.";
        header('Location: backoffice_esercizio.php');
        exit;
      } else {
        q("INSERT INTO p1_tratte (id_stazione_partenza,id_stazione_arrivo,distanza_km) VALUES (?,?,?)", [$sp, $sa, $dkm], 'iid');
        $_SESSION['flash_success'][] = "Tratta creata (" . number_format($dkm, 3, ',', '.') . " km).";
        header('Location: backoffice_esercizio.php');
        exit;
      }
    }
  }
}
// Queries DB
$stazioni = q("SELECT id_stazione, nome, km_progressivo AS pos FROM p1_stazioni ORDER BY km_progressivo ASC")->get_result()->fetch_all(MYSQLI_ASSOC);
// Mezzi non assegnati a nessun treno
$mezzi = q("
  SELECT m.*
  FROM p1_materiale_rotabile m
  LEFT JOIN p1_treni_mezzi tm ON tm.id_mezzo = m.id_mezzo
  WHERE tm.id_mezzo IS NULL
  ORDER BY m.tipo, m.modello
")->get_result()->fetch_all(MYSQLI_ASSOC);
$treni = q("SELECT * FROM p1_treni WHERE disattivo=0 ORDER BY codice")->get_result()->fetch_all(MYSQLI_ASSOC);
$comp = q("SELECT tm.*, m.tipo, m.modello FROM p1_treni_mezzi tm JOIN p1_materiale_rotabile m ON m.id_mezzo=tm.id_mezzo ORDER BY id_treno")->get_result()->fetch_all(MYSQLI_ASSOC);
$tratte = q("
  SELECT
    t.id_tratta,
    sp.nome  AS partenza,
    sa.nome  AS arrivo,
    t.distanza_km,
    sp.km_progressivo AS km_sp,
    sa.km_progressivo AS km_sa
  FROM p1_tratte t
  JOIN p1_stazioni sp ON sp.id_stazione = t.id_stazione_partenza
  JOIN p1_stazioni sa ON sa.id_stazione = t.id_stazione_arrivo
  ORDER BY sp.km_progressivo ASC, sa.km_progressivo ASC
")->get_result()->fetch_all(MYSQLI_ASSOC);
$corse = q("SELECT c.*, t.codice, sp.nome AS partenza, sa.nome AS arrivo FROM p1_corse c JOIN p1_treni t ON t.id_treno=c.id_treno JOIN p1_tratte tr ON tr.id_tratta=c.id_tratta JOIN p1_stazioni sp ON sp.id_stazione=tr.id_stazione_partenza JOIN p1_stazioni sa ON sa.id_stazione=tr.id_stazione_arrivo ORDER BY c.data DESC, c.ora_partenza DESC")->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/header.php';

// Gestione messaggi flash
if (!empty($_SESSION['flash_success'])) {
  foreach ($_SESSION['flash_success'] as $m) {
    echo "<div class='alert success'>" . htmlspecialchars($m) . "</div>";
  }
  unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
  foreach ($_SESSION['flash_error'] as $m) {
    echo "<div class='alert error'>" . htmlspecialchars($m) . "</div>";
  }
  unset($_SESSION['flash_error']);
}
?>

<h2>Backoffice di esercizio</h2>
<div class="grid-quad">
  <section class="card section-add">
    <h3>Modifica materiale rotabile</h3>
    <form method="post" class="form">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

      <h4>Nuovo materiale</h4>
      <input type="hidden" name="azione" value="add_mezzo">
      <label>Tipo
        <select name="tipo">
          <option value="locomotiva">Locomotiva</option>
          <option value="carrozza">Carrozza</option>
          <option value="automotrice">Automotrice</option>
          <option value="bagagliaio">Bagagliaio</option>
        </select>
      </label>
      <label>Modello <input type="text" name="modello" required></label>
      <label>Posti <input type="number" name="posti" min="0" required></label>
      <button class="btn">Salva</button>
    </form>
    <form method="post" class="form" style="margin-top:1rem">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

      <h4>Rimuovi materiale esistente</h4>
      <input type="hidden" name="azione" value="del_mezzo">
      <label>Mezzo rotabile
        <select name="id_mezzo" required>
          <?php foreach ($mezzi as $m): ?>
            <option value="<?= $m['id_mezzo'] ?>">
              <?= $m['tipo'] ?> - <?= $m['modello'] ?> (<?= (int)$m['posti'] ?> posti) [ID: <?= $m['id_mezzo'] ?>]
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn" onclick="return confirm('Confermi la rimozione del materiale selezionato? L\'operazione è irreversibile.');">Rimuovi</button>
    </form>
  </section>

  <?php
  // Carica composizioni per tutti i treni attivi
  $comps = q("
  SELECT tm.id_treno, tm.id_mezzo,
         m.tipo, m.modello, m.posti
  FROM p1_treni_mezzi tm
  JOIN p1_materiale_rotabile m ON m.id_mezzo = tm.id_mezzo
")->get_result()->fetch_all(MYSQLI_ASSOC);

  $byTrain = [];
  foreach ($comps as $c) {
    $byTrain[(int)$c['id_treno']][] = [
      'id_mezzo' => (int)$c['id_mezzo'],
      'label'    => $c['tipo'] . ' ' . $c['modello'] . ' (' . $c['posti'] . ')'
    ];
  }
  ?>

  <script>
    window.trainComps = <?= json_encode($byTrain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>

  <section class="card section-compose">
    <h3>Componi treno</h3>
    <form method="post" class="form inline">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

      <input type="hidden" name="azione" value="compose">
      <label>Treno
        <select name="id_treno" id="compose-id-treno">
          <?php foreach ($treni as $t): ?>
            <option value="<?= $t['id_treno'] ?>"><?= $t['codice'] ?> (cap: <?= $t['posti_totali'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Mezzo
        <select name="id_mezzo" id="compose-id-mezzo">
          <?php foreach ($mezzi as $m): ?>
            <option value="<?= $m['id_mezzo'] ?>"><?= $m['tipo'] ?> <?= $m['modello'] ?> (<?= $m['posti'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Quantità <input type="number" name="quantita" min="1" value="1"></label>
      <button class="btn">Aggiungi</button>
    </form>

    <div class="form inline" style="margin-top:.5rem; gap:.5rem; align-items:end;">
      <!-- Selettore della componente attualmente in composizione per il treno scelto -->
      <label>Componente nel treno
        <select id="compose-id-mezzo-rem" class="input" disabled>
          <option value="">— nessuna —</option>
        </select>
      </label>

      <!-- Elimina treno (usa il treno selezionato sopra) -->
      <form method="post" class="form inline" id="form-delete-treno" onsubmit="return confirm('Eliminare il treno selezionato?');">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

        <input type="hidden" name="azione" value="delete_treno">
        <input type="hidden" name="id_treno" id="delete-id-treno" value="">
        <button type="submit" class="btn">Elimina treno</button>
      </form>

      <!-- Rimuove componente (usa treno + componente selezionati) -->
      <form method="post" class="form inline" id="form-remove-mezzo" onsubmit="return confirm('Rimuovere questa componente dal treno selezionato?');">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

        <input type="hidden" name="azione" value="remove_mezzo_treno">
        <input type="hidden" name="id_treno" id="remove-id-treno" value="">
        <input type="hidden" name="id_mezzo" id="remove-id-mezzo" value="">
        <button type="submit" class="btn">Rimuovi componente</button>
      </form>
    </div>
    <div class="table table-comp">
      <div class="row head">
        <div>Treno</div>
        <div>Composizione</div>
      </div>
      <?php foreach ($treni as $t): ?>
        <div class="row">
          <div><?= $t['codice'] ?> (cap: <?= $t['posti_totali'] ?>)</div>
          <div class="composizione-box">
            <?php foreach ($comp as $c) if ($c['id_treno'] == $t['id_treno']): ?>
              <span class="comp-item"><?= $c['tipo'] ?> <?= $c['modello'] ?> x<?= $c['quantita'] ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <script>
    (function() {
      const selTreno = document.getElementById('compose-id-treno');
      const selRem = document.getElementById('compose-id-mezzo-rem');
      const delTrenoInp = document.getElementById('delete-id-treno');
      const remTrenoInp = document.getElementById('remove-id-treno');
      const remMezzoInp = document.getElementById('remove-id-mezzo');

      function populateRemOptions() {
        const idTreno = selTreno.value;
        const comps = (window.trainComps && window.trainComps[idTreno]) ? window.trainComps[idTreno] : [];
        selRem.innerHTML = '';
        if (!comps.length) {
          selRem.disabled = true;
          selRem.appendChild(new Option('— nessuna —', ''));
        } else {
          selRem.disabled = false;
          comps.forEach(c => selRem.appendChild(new Option(c.label, c.id_mezzo)));
        }
        // Aggiorna hidden per i form
        delTrenoInp.value = idTreno || '';
        remTrenoInp.value = idTreno || '';
        remMezzoInp.value = selRem.value || '';
      }

      selTreno.addEventListener('change', populateRemOptions);
      selRem.addEventListener('change', () => {
        remMezzoInp.value = selRem.value || '';
      });

      // init
      populateRemOptions();
    })();
  </script>

  <section class="card section-new">
    <h3>Crea corsa</h3>
    <form method="post" class="form">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

      <h4>Nuovo treno</h4>
      <input type="hidden" name="azione" value="add_treno">
      <label>Codice <input type="text" name="codice" required></label>
      <label>Velocità media <input type="number" step="0.1" name="velocita_media" value="<?= VEL_MEDIA_DEFAULT ?>"></label>
      <button class="btn">Crea treno</button>
    </form>
    <form method="post" id="form-subtratta" class="form">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

      <input type="hidden" name="azione" value="add_subtratta">
      <h4>Nuova tratta</h4>
      <div class="grid-3">
        <label>Stazione di partenza
          <select name="id_stazione_partenza" required>
            <?php foreach ($stazioni as $s): ?>
              <option value="<?= $s['id_stazione'] ?>">
                <?= htmlspecialchars($s['nome']) ?> (km <?= number_format($s['pos'], 3, ',', '') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Stazione di arrivo
          <select name="id_stazione_arrivo" required>
            <?php foreach ($stazioni as $s): ?>
              <option value="<?= $s['id_stazione'] ?>">
                <?= htmlspecialchars($s['nome']) ?> (km <?= number_format($s['pos'], 3, ',', '') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <button class="btn">Crea tratta</button>
    </form>
  </section>

  <section class="card section-schedule">
    <h3>Programmazione corse</h3>
    <form method="post" class="form">
  <?php if (ENABLE_CSRF): ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>

      <input type="hidden" name="azione" value="add_corsa">
      <h4>Nuova corsa</h4>
      <label>Treno
        <select name="id_treno">
          <?php foreach ($treni as $t): ?><option value="<?= $t['id_treno'] ?>"><?= $t['codice'] ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Tratta
        <select name="id_tratta">
          <?php foreach ($tratte as $tr): ?>
            <option value="<?= $tr['id_tratta'] ?>">
              <?= $tr['partenza'] ?> (km <?= number_format($tr['km_sp'], 3, ',', '') ?>)
              → <?= $tr['arrivo'] ?> (km <?= number_format($tr['km_sa'], 3, ',', '') ?>)
              — <?= number_format($tr['distanza_km'], 1, ',', '') ?> km
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Data <input type="date" name="data" required></label>
      <label>Ora partenza <input type="time" name="ora_partenza" required></label>
      <button class="btn">Aggiungi corsa</button>
    </form>
  </section>

  <section class="card card-wide">
    <h3>Corse attive</h3>
    <div class="table table-corse">
      <div class="row head">
        <div>ID</div>
        <div>Data</div>
        <div>Tratta</div>
        <div>Treno</div>
        <div>Stato</div>
      </div>
      <?php if (empty($corse)): ?>
        <div class="row">
          <div class="cell-nowrap" style="grid-column: 1 / -1; text-align: center; color: #999;">
            Non sono presenti corse attive
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($corse as $c): ?>
          <div class="row">
            <div>#<?= $c['id_corsa'] ?></div>
            <div class="cell-nowrap cell-date">
              <?= htmlspecialchars($c['data']) ?>
              <?= substr($c['ora_partenza'], 0, 5) ?> → <?= substr($c['ora_arrivo'], 0, 5) ?>
            </div>
            <div class="cell-nowrap">
              <?= htmlspecialchars($c['partenza']) ?> → <?= htmlspecialchars($c['arrivo']) ?>
            </div>
            <div><?= htmlspecialchars($c['codice']) ?></div>
            <div><?= $c['cancellata'] ? 'Cancellata' : 'Attiva' ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="card card-wide">
    <h3 id="richieste">Richieste treni straordinari</h3>
    <?php
    $res = q("SELECT r.*, u.nome FROM p1_richieste_treni r
          JOIN p1_utenti u ON u.id_utente = r.id_admin
          ORDER BY r.creata_il DESC");
    $reqs = $res->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($reqs as $r): ?>
      <div class="card">
        <p><strong>Da:</strong> <?= $r['nome'] ?></p>
        <p><strong>Messaggio:</strong> <?= htmlspecialchars($r['messaggio']) ?></p>
        <p><strong>Stato:</strong> <?= htmlspecialchars($r['stato']) ?></p><?php if (!empty($r['risposta'])): ?><p><strong>Risposta:</strong> <?= htmlspecialchars($r['risposta']) ?></p><?php endif; ?>
        <?php if ($r['stato'] === 'In attesa'): ?>
          <a class="btn" href="risposta_richiesta.php?id=<?= $r['id_richiesta'] ?>">Rispondi</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>