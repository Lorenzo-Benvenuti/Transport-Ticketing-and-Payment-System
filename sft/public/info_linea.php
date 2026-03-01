<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
redirect_front_to_backoffice_if_needed();
require_once __DIR__ . '/../includes/header.php';
?>

<section class="hero">
  <h2>Informazioni linea</h2>
  <p>Stazioni, tipologie di materiale rotabile e treni disponibili.</p>
</section>

<!-- Stile pagina -->
<section class="content info-linea">
  <style>
    .info-linea {
      --gap: 24px;
      text-align: center;
    }

    .info-linea .stack {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: var(--gap);
      align-items: stretch;
    }

    @media (max-width: 1200px) {
      .info-linea .stack {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 780px) {
      .info-linea .stack {
        grid-template-columns: 1fr;
      }
    }

    .info-linea .card h3 {
      margin: 0 0 8px 0;
    }

    .info-linea .table-scroll {
      flex: 1 1 auto;
    }

    .info-linea .muted {
      color: var(--muted);
    }

    .info-linea .clean {
      margin: 8px 0 0;
      padding-left: 18px;
    }

    .info-linea .clean li {
      margin: 6px 0;
    }

    .info-linea .subtab {
      display: none;
    }

    .info-linea .subtab.active {
      display: block;
    }

    @media (min-width: 781px) {
      .info-linea .stack {
        grid-template-columns: 1fr 1fr 1.12fr;
      }
    }

    .info-linea .card-treni .table-scroll {
      overflow: visible;
      max-height: none;
    }

    .info-linea .subtab table.table {
      width: 100%;
      table-layout: auto;
    }

    .info-linea .subtab thead th {
      background: #0f2235;
      color: #b9d2e7;
      font-weight: 600;
    }

    .info-linea .subtab table.table {
      width: 100% !important;
      table-layout: auto;
    }

    .info-linea .subtab table.table colgroup col:first-child {
      width: auto;
    }

    .info-linea .subtab table.table colgroup col:last-child {
      width: 140px;
    }

    .info-linea .table {
      width: 100%;
    }

    .info-linea .table thead th {
      background: #0f2235;
      color: #b9d2e7;
      font-weight: 600;
    }

    .info-linea .table th,
    .info-linea .table td {
      text-align: center;
    }

    .info-linea .table-stazioni th:nth-child(2),
    .info-linea .table-stazioni td:nth-child(2) {
      text-align: left;
    }

    .info-linea .table-tipologie th:first-child,
    .info-linea .table-tipologie td:first-child {
      text-align: left;
    }

    .info-linea .table-treni th:first-child,
    .info-linea .table-treni td:first-child {
      text-align: left;
    }

    .info-linea .table-scroll {
      width: 100%;
    }

    .info-linea .card-treni .table-scroll {
      overflow: visible;
      max-height: none;
    }

    @media (min-width: 781px) {
      .info-linea .stack {
        grid-template-columns: 1fr 1fr 1.2fr;
      }
    }

    .info-linea .table {
      width: 100%;
      table-layout: fixed;
    }

    .info-linea .table th,
    .info-linea .table td {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .info-linea .table-stazioni th:nth-child(2),
    .info-linea .table-stazioni td:nth-child(2) {
      text-align: left;
    }

    .info-linea .table-tipologie th:first-child,
    .info-linea .table-tipologie td:first-child {
      text-align: left;
    }

    .info-linea .table-treni th:first-child,
    .info-linea .table-treni td:first-child {
      text-align: left;
    }

    .info-linea .card .card-body {
      display: flex;
      flex-direction: column;
    }

    .info-linea .card .card-body>.table-scroll {
      flex: 1 1 auto;
      width: 100%;
      padding: 0;
      overflow: visible;
    }

    .info-linea .subtab {
      display: block;
      width: 100%;
    }

    .info-linea .subtab .table {
      display: table;
      width: 100% !important;
      table-layout: fixed !important;
    }

    .info-linea .subtab .table thead th,
    .info-linea .subtab .table tbody td {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .info-linea .table-tipologie colgroup col:first-child {
      width: 70% !important;
    }

    .info-linea .table-tipologie colgroup col:last-child {
      width: 30% !important;
    }

    .info-linea .card h3 {
      text-align: center;
    }
  </style>

  <div class="stack">
    <div class="panel">
      <h3>Stazioni</h3>
      <?php
      $stazioni = q("SELECT nome, km_progressivo FROM p1_stazioni ORDER BY km_progressivo ASC")->get_result();
      if ($stazioni && $stazioni->num_rows > 0): ?>
        <table class="table table-stazioni">
          <colgroup>
            <col style="width:70px">
            <col style="width:65%">
            <col style="width:35%">
          </colgroup>
          <thead>
            <tr>
              <th>#</th>
              <th>Stazione / Fermata</th>
              <th>Posizione (Km)</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1;
            while ($s = $stazioni->fetch_assoc()): ?>
              <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($s['nome']); ?></td>
                <td><?php echo number_format((float)$s['km_progressivo'], 3, ',', '.'); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p><em>Nessuna stazione presente.</em></p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h3>Materiale rotabile</h3>
      <?php
      $res = q("SELECT tipo, modello, posti FROM p1_materiale_rotabile ORDER BY 
                  FIELD(tipo,'Locomotiva','Automotrice','Carrozza','Bagagliaio'), modello ASC")->get_result();
      if ($res && $res->num_rows > 0):
        $gruppi = [];
        while ($row = $res->fetch_assoc()) {
          $gruppi[$row['tipo']][] = $row;
        }
      ?>

        </select>
        <div class="table-scroll">
          <?php $i = 0;
          foreach ($gruppi as $tipo => $items): $i++; ?>
            <div class="subtab<?php echo $i === 1 ? ' active' : ''; ?>" data-tipo="<?php echo htmlspecialchars($tipo); ?>">
              <h4 class="muted"><?php echo htmlspecialchars($tipo); ?></h4>
              <table class="table table-tipologie">
                <colgroup>
                  <col style="width:70%">
                  <col style="width:30%">
                </colgroup>
                <thead>
                  <tr>
                    <th>Modello</th>
                    <th>Posti</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $it): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($it['modello']); ?></td>
                      <td><?php echo (int)$it['posti']; ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endforeach; ?>
        </div>
        <script>
          (function() {
            const root = document.currentScript.parentElement;
            const sel = root.querySelector('#tipoSelect');
            const tabs = root.querySelectorAll('.subtab');

            function sync() {
              tabs.forEach(t => t.classList.toggle('active', t.getAttribute('data-tipo') === sel.value));
            }
            if (sel) sel.addEventListener('change', sync);
            if (sel && sel.options.length) {
              sel.value = sel.options[0].value;
              sync();
            }
          })();
        </script>
      <?php
      else: ?>
        <p><em>Nessun materiale rotabile presente.</em></p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h3>Treni</h3>
      <?php
      $treni = q("SELECT codice, velocita_media, posti_totali FROM p1_treni ORDER BY codice ASC")->get_result();
      if ($treni && $treni->num_rows > 0): ?>
        <table class="table table-treni">
          <colgroup>
            <col style="width:34%">
            <col style="width:33%">
            <col style="width:33%">
          </colgroup>
          <thead>
            <tr>
              <th>Codice</th>
              <th>Velocità media (km/h)</th>
              <th>Posti totali</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($t = $treni->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($t['codice']); ?></td>
                <td><?php echo number_format((float)$t['velocita_media'], 1, ',', '.'); ?></td>
                <td><?php echo (int)$t['posti_totali']; ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p><em>Nessun treno presente.</em></p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>