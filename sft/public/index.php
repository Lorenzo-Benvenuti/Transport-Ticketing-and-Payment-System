<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
redirect_front_to_backoffice_if_needed();
require_once __DIR__ . '/../includes/header.php';
?>

<section class="hero">
  <h2>Linea ferroviaria turistica — orari, acquisti e gestione</h2>
  <p>Consulta gli orari e le informazioni, acquista un biglietto e gestisci la linea dai backoffice:</p>
</section>

<section class="cards">
  <?php if (!empty($_SESSION['user']) && $_SESSION['user']['ruolo'] === RUOLO_UTENTE): ?>
    <a class="card" href="orari.php">
      <h3>Orari</h3>
      <p>Visualizza le corse disponibili.</p>
    </a>
  <?php endif; ?>

  <a class="card" href="info_linea.php">
    <h3>Informazioni linea</h3>
    <p>Fermate, treni e convogli.</p>
  </a>

  <?php if (!empty($_SESSION['user']) && $_SESSION['user']['ruolo'] === RUOLO_UTENTE): ?>
    <a class="card" href="acquisto.php">
      <h3>Acquista Biglietto</h3>
      <p>Scegli corsa e posto a sedere.</p>
    </a>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>