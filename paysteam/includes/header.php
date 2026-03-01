<?php
require_once __DIR__ . '/../config/constants.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$u = $_SESSION['user'] ?? null;
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
  <header class="topbar">
    <div class="brand"><?= htmlspecialchars(APP_NAME) ?></div>
    <nav>
      <?php if ($u): ?>
        <?php if ($u['ruolo'] === RUOLO_ESERCENTE): ?>
          <a href="esercente.php">Merchant</a>
        <?php else: ?>
          <a href="dashboard.php">Dashboard</a>
          <a href="movimenti.php">Transactions</a>
        <?php endif; ?>
        <a href="logout.php">Logout</a>
      <?php else: ?>
        <a href="index.php">Home</a>
        <a href="login.php">Login</a>
        <a href="login.php?area=1">Staff Area</a>
        <a href="register.php">Register</a>
      <?php endif; ?>
    </nav>
  </header>
  <main class="container">