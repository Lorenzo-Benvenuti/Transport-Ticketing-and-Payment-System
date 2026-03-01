<?php
require_once __DIR__ . '/../includes/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['user'] = null;
session_destroy();
header('Location: index.php');
exit;
