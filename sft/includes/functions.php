<?php
require_once __DIR__ . '/../../shared/security.php';
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../../shared/web.php';

function abort_page(string $message, int $code = 400, string $title = 'Errore')
{
  http_response_code($code);
  $safeMsg = htmlspecialchars($message);
  require __DIR__ . '/header.php';
  echo "<h2>" . htmlspecialchars($title) . "</h2>";
  echo "<div class=\"alert error\">{$safeMsg}</div>";
  echo '<a class="btn" href="index.php">Home</a>';
  require __DIR__ . '/footer.php';
  exit;
}

function client_ip(): string
{
  return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function rate_limit_login_or_register(string $bucket, int $max, int $windowSeconds): void
{
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (!isset($_SESSION['rate_limit'])) $_SESSION['rate_limit'] = [];
  $key = $bucket . ':' . client_ip();
  if (!rate_limiter_allow($_SESSION['rate_limit'], $key, $max, $windowSeconds)) {
    abort_page('Troppi tentativi. Riprova più tardi.', 429, 'Rate limit');
  }
}

function q($sql, $params = [], $types = '')
{
  global $conn;
  $stmt = $conn->prepare($sql);
  if ($params) {
    if (!$types) {
      foreach ($params as $p) {
        $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
      }
    }
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  return $stmt;
}

function get_stazioni()
{
  $res = q("SELECT * FROM p1_stazioni ORDER BY km_progressivo")->get_result();
  return $res->fetch_all(MYSQLI_ASSOC);
}

function corsa_disponibilita($id_corsa)
{
  $stmt = q("SELECT t.posti_totali AS tot, COUNT(b.id_biglietto) AS pren 
             FROM p1_corse c 
             JOIN p1_treni t ON t.id_treno=c.id_treno
             LEFT JOIN p1_biglietti b ON b.id_corsa=c.id_corsa
             WHERE c.id_corsa=? GROUP BY t.id_treno", [$id_corsa], 'i');
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) return [0, 0];
  return [(int)$row['tot'], (int)$row['pren']];
}

function tratta_distanza_km($id_tratta)
{
  $stmt = q("SELECT distanza_km FROM p1_tratte WHERE id_tratta=?", [$id_tratta], 'i');
  $row = $stmt->get_result()->fetch_assoc();
  return $row ? (float)$row['distanza_km'] : 0.0;
}

function calcola_prezzo($id_tratta)
{
  return round(tratta_distanza_km($id_tratta) * TARIFFA_KM, 2);
}

function posti_occupati($id_corsa)
{
  $stmt = q("SELECT COUNT(*) AS n FROM p1_biglietti WHERE id_corsa=?", [$id_corsa], 'i');
  return (int)$stmt->get_result()->fetch_assoc()['n'];
}

function posti_totali_treno($id_treno)
{
  $stmt = q("SELECT posti_totali FROM p1_treni WHERE id_treno=?", [$id_treno], 'i');
  $row = $stmt->get_result()->fetch_assoc();
  return $row ? (int)$row['posti_totali'] : 0;
}

function aggiorna_posti_totali_treno($id_treno)
{
  $stmt = q("SELECT SUM(m.posti * tm.quantita) AS posti 
             FROM p1_treni_mezzi tm JOIN p1_materiale_rotabile m ON m.id_mezzo=tm.id_mezzo
             WHERE tm.id_treno=?", [$id_treno], 'i');
  $row = $stmt->get_result()->fetch_assoc();
  $posti = $row && $row['posti'] ? (int)$row['posti'] : 0;
  q("UPDATE p1_treni SET posti_totali=? WHERE id_treno=?", [$posti, $id_treno], 'ii');
  return $posti;
}

function _tratta_info($id_tratta)
{
  $stmt = q(
    "SELECT id_stazione_partenza AS sp, id_stazione_arrivo AS sa FROM p1_tratte WHERE id_tratta=?",
    [(int)$id_tratta],
    'i'
  );
  $row = $stmt->get_result()->fetch_assoc();
  return $row ? [(int)$row['sp'], (int)$row['sa']] : null;
}

// Ritorna [sp, sa, distanza_km] della tratta.
function _tratta_info_full($id_tratta)
{
  $stmt = q(
    "SELECT id_stazione_partenza AS sp, id_stazione_arrivo AS sa, distanza_km
     FROM p1_tratte WHERE id_tratta=?",
    [(int)$id_tratta],
    'i'
  );
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) return null;
  return [(int)$row['sp'], (int)$row['sa'], (float)$row['distanza_km']];
}

/**
 * Elenco occupazioni sulla stessa tratta fisica (A<->B), giorno dato.
 * Ogni item: ['start'=>sec, 'end'=>sec]
 */
function _occupazioni_tratta_fisica($data, $sp, $sa)
{
  // id_tratta in entrambi i versi
  $rs = q(
    "SELECT id_tratta FROM p1_tratte
     WHERE (id_stazione_partenza=? AND id_stazione_arrivo=?)
        OR (id_stazione_partenza=? AND id_stazione_arrivo=?)",
    [$sp, $sa, $sa, $sp],
    'iiii'
  )->get_result();

  $ids = [];
  while ($r = $rs->fetch_assoc()) $ids[] = (int)$r['id_tratta'];
  if (!$ids) return [];

  $in = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));

  $rs2 = q(
    "SELECT ora_partenza, ora_arrivo
     FROM p1_corse
     WHERE data=? AND cancellata=0 AND id_tratta IN ($in)",
    array_merge([$data], $ids),
    's' . $types
  )->get_result();

  $occ = [];
  while ($r = $rs2->fetch_assoc()) {
    $occ[] = [
      'start' => hhmmss_to_seconds($r['ora_partenza']),
      'end'   => hhmmss_to_seconds($r['ora_arrivo']),
    ];
  }
  usort($occ, fn($a, $b) => $a['start'] <=> $b['start']);
  return $occ;
}

/**
 * Pianifica la corsa su subtratte non condivisibili.
 * - Parte dall'ora richiesta (HH:MM:SS).
 * - Per ogni subtratta: se all'istante di ingresso è libera, la "occupa" fino a uscita;
 * Altrimenti, attende al confine precedente.
 * Restituisce: [ora_partenza_eff, ora_arrivo, attesa_tot_min].
*/
function calcola_orari_corsa($id_treno, $id_tratta, $data, $ora_partenza_richiesta)
{
  $vel_kmh = get_velocita_treno($id_treno);          // km/h dal treno
  $vel_kmh = max(1.0, $vel_kmh ?: VEL_MEDIA_DEFAULT);

  $subs = get_subtratte($id_tratta);

  // Se non ci sono subtratte definite: tratta = unico blocco
  if (!$subs) {
    $row  = q("SELECT distanza_km FROM p1_tratte WHERE id_tratta=?", [(int)$id_tratta], 'i')
      ->get_result()->fetch_assoc();
    $dist = $row ? (float)$row['distanza_km'] : 0.0;

    $start = hhmmss_to_seconds($ora_partenza_richiesta);
    $dur   = (int) round(($dist / $vel_kmh) * 3600);
    if ($dur < 1) $dur = 1;

    $end   = $start + $dur;
    return [seconds_to_hhmmss($start), seconds_to_hhmmss($end), 0];
  }

  // Tempo di ingresso nella prima subtratta (richiesto)
  $t_ingresso = hhmmss_to_seconds($ora_partenza_richiesta);

  // Attesa complessiva in secondi
  $attesa_tot = 0;

  // Mappa occupazioni esistenti per tutte le subtratte fisiche della tratta (entrambi i versi)
  // Chiave: phys_key(stazione_from, stazione_to) -> [ [start,end], ... ]
  $occ_map = mappa_occupazioni_subtratte($id_tratta, $data);

  // Primo istante effettivo di ingresso in linea (dopo eventuale attesa)
  $first_entry_time = null;

  foreach ($subs as $s) {
    $dur_sub = (int) round(($s['km'] / $vel_kmh) * 3600);
    if ($dur_sub < 1) $dur_sub = 1;

    $k = phys_key($s['from'], $s['to']);  // Chiave fisica min-max
    if (!isset($occ_map[$k])) {
      $occ_map[$k] = [];
    }

    // Tentativo iniziale: arrivo dalla subtratta precedente
    $t_start = $t_ingresso;

    // Loop finché non troviamo un intervallo [t_start, t_end) libero
    while (true) {
      $t_end = $t_start + $dur_sub;
      $conflict = false;

      foreach ($occ_map[$k] as $iv) {
        // Sovrapposizione se gli intervalli si intersecano
        if ($t_start < $iv['end'] && $t_end > $iv['start']) {
          // Sposta l'ingresso alla fine dell'occupazione esistente
          $shift = $iv['end'] - $t_start;
          if ($shift > 0) {
            $attesa_tot += $shift;
            $t_start = $iv['end'];
            $t_end   = $t_start + $dur_sub;
            $conflict = true;
          }
          // Dopo lo shift ricontrolla tutta la lista dall'inizio
          break;
        }
      }

      if (!$conflict) {
        // Intervallo [t_start, t_end) libero sulla subtratta k
        break;
      }
    }

    // Intervallo definitivo di occupazione per questa subtratta
    $t_end = $t_start + $dur_sub;

    // Primo ingresso effettivo in linea
    if ($first_entry_time === null) {
      $first_entry_time = $t_start;
    }

    // Aggiungi occupazione del treno per chi verrà dopo
    $occ_map[$k][] = ['start' => $t_start, 'end' => $t_end];
    usort($occ_map[$k], function ($a, $b) {
      return $a['start'] <=> $b['start'];
    });

    // Uscita da questa subtratta = ingresso nella successiva
    $t_ingresso = $t_end;
  }

  if ($first_entry_time === null) {
    $first_entry_time = hhmmss_to_seconds($ora_partenza_richiesta);
  }

  $ora_partenza_eff = seconds_to_hhmmss($first_entry_time);
  $ora_arrivo       = seconds_to_hhmmss($t_ingresso);
  $attesa_min       = (int) round($attesa_tot / 60);

  return [$ora_partenza_eff, $ora_arrivo, $attesa_min];
}

function phys_key($a, $b)
{
  $a = (int)$a;
  $b = (int)$b;
  return ($a < $b) ? ($a . '-' . $b) : ($b . '-' . $a);
}

function get_velocita_treno($id_treno)
{
  $v = VEL_MEDIA_DEFAULT;
  $row = q("SELECT velocita_media FROM p1_treni WHERE id_treno=?", [(int)$id_treno], 'i')->get_result()->fetch_assoc();
  if ($row && (float)$row['velocita_media'] > 0) $v = (float)$row['velocita_media'];
  return max(1.0, (float)$v);
}

function get_tratta_head($id_tratta)
{
  $r = q(
    "SELECT id_stazione_partenza AS sp, id_stazione_arrivo AS sa FROM p1_tratte WHERE id_tratta=?",
    [(int)$id_tratta],
    'i'
  )->get_result()->fetch_assoc();
  return $r ? [(int)$r['sp'], (int)$r['sa']] : [null, null];
}

function get_subtratte($id_tratta)
{
  // Legge estremi della tratta con i km progressivi
  $row = q(
    "SELECT 
       t.id_stazione_partenza AS sp,
       t.id_stazione_arrivo   AS sa,
       sp.km_progressivo      AS km_sp,
       sa.km_progressivo      AS km_sa
     FROM p1_tratte t
     JOIN p1_stazioni sp ON sp.id_stazione = t.id_stazione_partenza
     JOIN p1_stazioni sa ON sa.id_stazione = t.id_stazione_arrivo
     WHERE t.id_tratta = ?",
    [(int)$id_tratta],
    'i'
  )->get_result()->fetch_assoc();

  if (!$row) return [];

  $sp_id = (int)$row['sp'];
  $sa_id = (int)$row['sa'];
  $km_sp = (float)$row['km_sp'];
  $km_sa = (float)$row['km_sa'];

  $km_min = min($km_sp, $km_sa);
  $km_max = max($km_sp, $km_sa);

  // Tutte le stazioni lungo il segmento fisico tra sp e sa (estremi inclusi)
  $rs = q(
    "SELECT id_stazione, km_progressivo
       FROM p1_stazioni
      WHERE km_progressivo BETWEEN ? AND ?
      ORDER BY km_progressivo ASC",
    [$km_min, $km_max],
    'dd'
  )->get_result();

  $stazioni = $rs->fetch_all(MYSQLI_ASSOC);
  if (!$stazioni || count($stazioni) < 2) {
    // Come fallback, usa tratta intera come unico blocco
    return [[
      'id_sub' => 0,
      'from'   => $sp_id,
      'to'     => $sa_id,
      'km'     => abs($km_sa - $km_sp),
      'seq'    => 1,
    ]];
  }

  // Se la tratta è "all’indietro", inverte l’ordine
  if ($km_sp > $km_sa) {
    $stazioni = array_reverse($stazioni);
  }

  $out = [];
  for ($i = 0; $i < count($stazioni) - 1; $i++) {
    $from = (int)$stazioni[$i]['id_stazione'];
    $to   = (int)$stazioni[$i + 1]['id_stazione'];
    $km_f = (float)$stazioni[$i]['km_progressivo'];
    $km_t = (float)$stazioni[$i + 1]['km_progressivo'];

    $out[] = [
      'id_sub' => 0,
      'from'   => $from,
      'to'     => $to,
      'km'     => abs($km_t - $km_f),
      'seq'    => $i + 1,
    ];
  }

  return $out;
}

/**
 * Calcola automaticamente gli intervalli di occupazione per tutte le subtratte della tratta,
 * per il giorno $data, sulla base delle corse già inserite (p1_corse) e della velocità dei loro treni.
 * Ritorna: array associativo [id_subtratta => [ [start,end], [start,end], ... ] ] con start
 * end in secondi (HH:MM:SS -> sec)
*/
function mappa_occupazioni_subtratte($id_tratta, $data)
{
  // 1) Estremi della tratta attuale
  $head = q(
    "SELECT id_stazione_partenza AS sp, id_stazione_arrivo AS sa
             FROM p1_tratte WHERE id_tratta=?",
    [(int)$id_tratta],
    'i'
  )->get_result()->fetch_assoc();
  if (!$head) return [];

  $sp = (int)$head['sp'];
  $sa = (int)$head['sa'];

  // 2) Trova tutte le tratte fisicamente equivalenti (entrambi i versi)
  $rs_tr = q(
    "SELECT id_tratta FROM p1_tratte
              WHERE (id_stazione_partenza=? AND id_stazione_arrivo=?)
                 OR (id_stazione_partenza=? AND id_stazione_arrivo=?)",
    [$sp, $sa, $sa, $sp],
    'iiii'
  )->get_result();

  $tratte_ids = [];
  while ($r = $rs_tr->fetch_assoc()) $tratte_ids[] = (int)$r['id_tratta'];
  if (!$tratte_ids) return [];

  // 3) Prepara mappa: chiave fisica "min-max" -> lista occupazioni [start,end]
  $occ = [];

  // 4) Precarica subtratte (dinamiche) per ognuna delle tratte equivalenti
  $subs_by_tratta = [];
  foreach ($tratte_ids as $tid) {
    $arr = [];
    foreach (get_subtratte($tid) as $s) {
      $arr[] = [
        'sf' => (int)$s['from'],
        'st' => (int)$s['to'],
        'km' => (float)$s['km'],
        'k'  => phys_key($s['from'], $s['to']),
      ];
    }
    $subs_by_tratta[$tid] = $arr;
  }

  // 5) Prende tutte le corse del giorno su queste tratte
  $in  = implode(',', array_fill(0, count($tratte_ids), '?'));
  $typ = str_repeat('i', count($tratte_ids));

  $rs_c = q(
    "SELECT c.id_tratta, c.id_treno, c.ora_partenza, t.velocita_media
             FROM p1_corse c
             JOIN p1_treni t ON t.id_treno=c.id_treno
             WHERE c.data=? AND c.cancellata=0 AND c.id_tratta IN ($in)",
    array_merge([$data], $tratte_ids),
    's' . $typ
  )->get_result();

  while ($c = $rs_c->fetch_assoc()) {
    $tid  = (int)$c['id_tratta'];
    $vel  = max(1.0, (float)$c['velocita_media'] ?: VEL_MEDIA_DEFAULT);
    $tcur = hhmmss_to_seconds($c['ora_partenza']);

    $subs = $subs_by_tratta[$tid] ?? [];

    // Se non ci sono subtratte definite, usa la tratta intera come 1 blocco fisico
    if (!$subs) {
      $k = phys_key($sp, $sa);
      $row = q("SELECT distanza_km FROM p1_tratte WHERE id_tratta=?", [$tid], 'i')->get_result()->fetch_assoc();
      $dur = (int) round((($row ? (float)$row['distanza_km'] : 0.0) / $vel) * 3600);
      $occ[$k][] = ['start' => $tcur, 'end' => $tcur + max(1, $dur)];
      continue;
    }

    // Distribuisce la corsa sulle sue subtratte; accumula occupazioni per chiave fisica
    foreach ($subs as $s) {
      $dur = (int) round(($s['km'] / $vel) * 3600);
      $dur = max(1, $dur);
      $k   = $s['k'];
      if (!isset($occ[$k])) $occ[$k] = [];
      $occ[$k][] = ['start' => $tcur, 'end' => $tcur + $dur];
      $tcur += $dur;
    }
  }

  // 6) Ordina liste
  foreach ($occ as $k => &$lst) {
    usort($lst, fn($a, $b) => $a['start'] <=> $b['start']);
  }
  return $occ;
}

function treno_ultima_posizione($id_treno, $data, $ora)
{
  $stmt = q("SELECT c.data, c.ora_partenza, c.ora_arrivo, tr.id_stazione_partenza AS sp, tr.id_stazione_arrivo AS sa
             FROM p1_corse c
             JOIN p1_tratte tr ON tr.id_tratta=c.id_tratta
             WHERE c.id_treno=? AND c.cancellata=0
               AND (c.data < ? OR (c.data = ? AND c.ora_arrivo <= ?))
             ORDER BY c.data DESC, c.ora_arrivo DESC
             LIMIT 1", [(int)$id_treno, $data, $data, $ora], 'isss');
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) return null;
  return [(int)$row['sa'], $row['data'], $row['ora_arrivo']];
}

/**
 * Impedisce corse sovrapposte sulla stessa tratta.
 * Si assume che ogni "tratta" rappresenti l'arco tra due stazioni adiacenti.
*/
function conflitto_sulla_stessa_tratta($id_tratta, $data, $ora_partenza, $ora_arrivo)
{
  $stmt = q(
    "SELECT COUNT(*) AS n FROM p1_corse
             WHERE id_tratta=? AND data=? AND cancellata=0
               AND (ora_partenza < ? AND ? < ora_arrivo)",
    [(int)$id_tratta, $data, $ora_arrivo, $ora_partenza],
    'isss'
  );
  $row = $stmt->get_result()->fetch_assoc();
  return $row && (int)$row['n'] > 0;
}

/**
 * Consente al massimo 2 partenze per stazione e solo verso destinazioni diverse.
*/
function conflitto_partenze_stazione($id_stazione_partenza, $id_stazione_arrivo, $data, $ora_partenza)
{
  $stmt = q(
    "SELECT tr.id_stazione_arrivo AS sa
             FROM p1_corse c JOIN p1_tratte tr ON tr.id_tratta=c.id_tratta
             WHERE c.data=? AND c.ora_partenza=? AND tr.id_stazione_partenza=? AND c.cancellata=0",
    [$data, $ora_partenza, (int)$id_stazione_partenza],
    'ssi'
  );
  $res = $stmt->get_result();
  $dest = [];
  while ($r = $res->fetch_assoc()) {
    $dest[] = (int)$r['sa'];
  }
  if (count($dest) === 0) return false;
  $dest = array_unique($dest);
  // Se già esistono due partenze con destinazioni diverse, blocca l'inserimento
  if (count($dest) >= 2) return true;
  // Se già esiste una partenza verso una destinazione, consente l'inserimento di un'ulteriore partenza solo se avente destinazione diversa
  return in_array((int)$id_stazione_arrivo, $dest, true);
}

/**
 * Validazione posizioni cronologiche del treno per costruzione sottotratte e gestione partenze simultanee.
 * Ritorna stringa di errore oppure null se tutto corretto.
*/
function valida_corsa_operativa($id_treno, $id_tratta, $data, $ora_partenza, $ora_arrivo)
{
  // La sicurezza è gestita:
  // - a livello di subtratte da calcola_orari_corsa() + mappa_occupazioni_subtratte()
  // - a livello di treno da treno_ultima_posizione()
  // - a livello di stazione da conflitto_partenze_stazione()

  // Dettagli tratta
  $ti = _tratta_info($id_tratta);
  if (!$ti) {
    return "Tratta inesistente.";
  }
  list($sp, $sa) = $ti;

  // Partenze simultanee dalla stessa stazione nella stessa direzione
  if (conflitto_partenze_stazione($sp, $sa, $data, $ora_partenza)) {
    return "Conflitto: dalla stessa stazione non possono partire due treni contemporaneamente, " .
      "se non in direzioni opposte (questa direzione è già occupata).";
  }

  // Coerenza posizione treno
  $ultima = treno_ultima_posizione($id_treno, $data, $ora_partenza);
  if ($ultima === null) {
    // Nessuna corsa precedente: prima corsa del treno, lo consideriamo disponibile nella stazione di partenza
    return null;
  }

  list($stazione_last, $data_last, $ora_last) = $ultima;

  // Il treno deve essere fisicamente nella stazione di partenza
  if ((int)$stazione_last !== (int)$sp) {
    return "Il treno non è presente alla stazione di partenza alla data/ora indicate.";
  }

  // Non può ripartire prima di essere arrivato
  if ($data === $data_last && $ora_partenza < $ora_last) {
    return "Il treno non può ripartire prima dell'orario del suo ultimo arrivo.";
  }

  return null;
}

// Esclude corse sovrapposte per lo stesso treno
if (!function_exists('corsa_conflitto_treno')) {
  function corsa_conflitto_treno($id_treno, $data, $ora_partenza, $ora_arrivo)
  {
    $id_treno = (int)$id_treno;
    $stmt = q(
      "SELECT COUNT(*) AS n
               FROM p1_corse
               WHERE id_treno = ?
                 AND data = ?
                 AND cancellata = 0
                 AND (ora_partenza < ? AND ? < ora_arrivo)",
      [$id_treno, $data, $ora_arrivo, $ora_partenza],
      'isss'
    );
    $row = $stmt->get_result()->fetch_assoc();
    return ($row && (int)$row['n'] > 0);
  }
}

function hhmmss_to_seconds($hhmmss)
{
  $parts = explode(':', $hhmmss);
  $h = isset($parts[0]) ? (int)$parts[0] : 0;
  $m = isset($parts[1]) ? (int)$parts[1] : 0;
  $s = isset($parts[2]) ? (int)$parts[2] : 0;
  return $h * 3600 + $m * 60 + $s;
}

function seconds_to_hhmmss($sec)
{
  $sec = ($sec % 86400 + 86400) % 86400; // Normalizza in 0..86399
  $h = floor($sec / 3600);
  $m = floor(($sec % 3600) / 60);
  $s = $sec % 60;
  return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

// csrf_token(), csrf_check() are provided by shared/web.php
