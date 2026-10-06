<?php
declare(strict_types=1);
$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ../verwaltung-login.php' . ($query !== '' ? '?' . $query : ''));
exit;
