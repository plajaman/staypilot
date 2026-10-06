<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/bootstrap.php';
$user = Auth::requireLogin();
$role = (string)($user['role'] ?? 'readonly');
if ($role === 'housekeeping') { header('Location: ../team/'); exit; }
if ($role === 'housekeeping_manager') { header('Location: ../team-manager/'); exit; }
if (in_array($role, ['admin','manager','reception'], true)) { header('Location: ../admin/#housekeeping'); exit; }
header('Location: ../admin/'); exit;
