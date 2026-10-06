<?php
declare(strict_types=1);
$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ../logout.php' . ($query !== '' ? '?' . $query : ''));
exit;
