<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';
$portal = Auth::normalizePortal((string)($_GET['portal'] ?? ''));
Auth::logout();
$target = match ($portal) {
    'team' => 'mitarbeiter-login.php',
    'manager' => 'leitung-login.php',
    'admin' => 'verwaltung-login.php',
    default => 'login.php',
};
header('Location: ' . $target);
exit;
