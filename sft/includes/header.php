<?php require_once __DIR__ . '/auth.php'; ?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title><?= APP_NAME ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body class="<?= isset($PAGE_CLASS) ? $PAGE_CLASS : '' ?>">
  <header class="app-header">
    <div class="container">
      <h1><?= APP_NAME ?></h1>
      <nav>
        <?php if (!empty($_SESSION['user']) && in_array($_SESSION['user']['ruolo'], [RUOLO_ADMIN, RUOLO_ESERCIZIO], true)): ?>
          <?php if ($_SESSION['user']['ruolo'] === RUOLO_ADMIN): ?>
            <a href="backoffice_admin.php">Admin Backoffice</a>
          <?php else: ?>
            <a href="backoffice_esercizio.php">Operations Backoffice</a>
          <?php endif; ?>
          <a href="logout.php" class="btn">Logout</a>
        <?php else: ?>
            <a href="index.php">Home</a>

            <?php if (!empty($_SESSION['user'])): ?>

              <?php if ($_SESSION['user']['ruolo'] === RUOLO_UTENTE): ?>
                <a href="acquisto.php">Buy Tickets</a>
              <?php endif; ?>

              <?php if ($_SESSION['user']['ruolo'] === RUOLO_ADMIN): ?>
                <a href="backoffice_admin.php">Backoffice Admin</a>
              <?php endif; ?>

              <?php if ($_SESSION['user']['ruolo'] === RUOLO_ESERCIZIO): ?>
                <a href="backoffice_esercizio.php">Operations Backoffice</a>
              <?php endif; ?>

              <a href="logout.php" class="btn">Logout</a>

            <?php else: ?>

              <a href="login.php">Login</a>
              <a href="login.php?area=1">Staff Area</a>
              <a href="register.php" class="btn">Register</a>

            <?php endif; ?>
          <?php endif; ?>
      </nav>
    </div>
  </header>
  <main class="container <?= isset($PAGE_CLASS) ? $PAGE_CLASS : "" ?>">